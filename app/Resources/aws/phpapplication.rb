#############################################################################
# AWS Elastic Beanstalk Host Manager
# Copyright 2011 Amazon.com, Inc. or its affiliates. All Rights Reserved.
#
# Licensed under the Amazon Software License (the “License”). You may
# not use this file except in compliance with the License. A copy of the
# License is located at
#
# http://aws.amazon.com/asl/
#
# or in the “license” file accompanying this file. This file is
# distributed on an “AS IS” BASIS, WITHOUT WARRANTIES OR CONDITIONS OF
# ANY KIND, express or implied. See the License for the specific
# language governing permissions and limitations under the License.
#
# File location: 
# /opt/elasticbeanstalk/srv/hostmanager/lib/elasticbeanstalk/hostmanager/applications/phpapplication.rb

require 'json'

module ElasticBeanstalk
  module HostManager
    module Applications

      class PHPApplication < Application
        class << self
          attr_reader :is_initialization_phase, :web_root_dir, :deploy_dir, :pre_deploy_script, :deploy_script, :post_deploy_script, :backup_dir, :vendor_dir, :error_start_index
        end

        # Directories, etc
        @web_root_dir       = '/var/www/html'
        @deploy_dir         = '/tmp/php-elasticbeanstalk-deployment'
        @pre_deploy_script  = '/tmp/php_pre_deploy_app.sh'
        @deploy_script      = '/tmp/php_deploy_app.sh'
        @post_deploy_script = '/tmp/php_post_deploy_app.sh'
        # Custom
        @backup_dir         = '/tmp'
        @vendor_dir         = '/var/www/vendor'

        @is_initialization_phase = false

        def self.ensure_configuration
          HostManager.log 'Writing environment config'
          ElasticBeanstalk::HostManager::Utils::PHPUtil.write_sdk_config(ElasticBeanstalk::HostManager.config.application['Environment Properties'])

          HostManager.log 'Updating php.ini options'
          ElasticBeanstalk::HostManager::Utils::PHPUtil.update_php_ini(ElasticBeanstalk::HostManager.config.container['Php.ini Settings'])

          HostManager.log 'Updating Apache options'
          ElasticBeanstalk::HostManager::Utils::ApacheUtil.update_httpd_conf(ElasticBeanstalk::HostManager.config.container['Php.ini Settings'])
        end

        def mark_in_initialization
          @is_initialization_phase = true
        end

        def pre_deploy
          
          HostManager.log "Starting pre-deployment."

          application_version_url = @version_info.to_url

          # Custom: Backup vendors folder to seppd up time needed for each deployment.
          # Without these lines all vendors will be downloaded each time.
          # output = `/usr/bin/sudo /bin/mv #{PHPApplication.web_root_dir}/vendor #{PHPApplication.backup_dir} 2>&1`
          # HostManager.log "Output: #{output}"
          # raise "Failed to backup vendors" if $?.exitstatus != 0
          # Commented out because after moving the files this error occours:
          # "symfony" has local modifications. Please revert or commit/push them before running this command again.
          
          HostManager.log "Re-building the Deployment Directory"
          output = `/usr/bin/sudo /bin/rm -rf #{PHPApplication.deploy_dir}`
          HostManager.log "Output: #{output}"
          output = `/usr/bin/sudo /bin/mkdir -p #{PHPApplication.deploy_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.deploy_dir}" unless File.directory?(PHPApplication.deploy_dir)

          HostManager.log "Changing owner, groups and permissions for the deployment directory."
          output = `/usr/bin/sudo /bin/chown elasticbeanstalk:elasticbeanstalk #{PHPApplication.deploy_dir}`
          HostManager.log "Output: #{output}"
          output = `/usr/bin/sudo /bin/chmod -Rf 0777 #{PHPApplication.deploy_dir}`
          HostManager.log "Output: #{output}"

          HostManager.log "Downloading / Validating Application version #{@version_info.version} from #{application_version_url}"
          output = `/usr/bin/time -f %e /usr/bin/wget -v --tries=10 --retry-connrefused -o #{PHPApplication.deploy_dir}/wget.log -O #{PHPApplication.deploy_dir}/application.zip "#{application_version_url}" 2>&1`
          HostManager.log "Output: #{output}"
          raise "Application download from #{application_version_url} failed" unless File.exists?("#{PHPApplication.deploy_dir}/application.zip")

          output = output.to_f * 1000
          HostManager.log "Application Download Time (ms): #{output}"
          HostManager.state.context[:metric].timings['AppDownloadTime'] = output unless HostManager.state.context[:metric].nil?

          output = `grep -o '(\\(.*\\/s\\))' #{PHPApplication.deploy_dir}/wget.log | sed 's/[\\(\\)]//g' 2>&1` if File.exists?("#{PHPApplication.deploy_dir}/wget.log")
          if output =~ /([0-9]+(?:\.[0-9]*))\s+(KB|MB|GB).*/
            output = $~[1].to_f
            output *= 1024 if $~[2] == 'MB' || $~[2] == 'GB'
            output *= 1024 if $~[2] == 'GB'
            output = output.to_i
            HostManager.log "Application Download Rate (kb/s): #{output}"
            HostManager.state.context[:metric].counters['AppDownloadRate'] = output unless HostManager.state.context[:metric].nil?
          elsif
            HostManager.log "Application Download Rate could not be determined: #{output}"
          end

          output = `/usr/bin/openssl dgst -md5 #{PHPApplication.deploy_dir}/application.zip 2>&1`
          output = $~[1] if output =~ /MD5\([^\)]+\)= (.*)/
          HostManager.log "Output: #{output}"
          raise "Application digest (#{output}) does not match expected digest (#{@version_info.digest})" unless output == @version_info.digest

        rescue
          HostManager.log("Version #{@version_info.version} PRE-DEPLOYMENT FAILED: #{$!}\n#{$@.join('\n')}")
          ex = ElasticBeanstalk::HostManager::DeployException.new("Version #{@version_info.version} pre-deployment failed: #{$!}")
          ex.output = output || ''
          raise ex
        end

        def deploy
          HostManager.log "Starting deployment."

          HostManager.log "Changing owner, groups and permissions for the deployment directory."
          output = `/usr/bin/sudo /bin/chown elasticbeanstalk:elasticbeanstalk #{PHPApplication.deploy_dir}`
          HostManager.log "Output: #{output}"
          output = `/usr/bin/sudo /bin/chmod -Rf 0777 #{PHPApplication.deploy_dir}`
          HostManager.log "Output: #{output}"

          HostManager.log "Creating #{PHPApplication.deploy_dir}/application and #{PHPApplication.deploy_dir}/backup"
          output = `/bin/mkdir -p #{PHPApplication.deploy_dir}/application 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.deploy_dir}/application" if $?.exitstatus != 0

          output = `/bin/mkdir -p #{PHPApplication.deploy_dir}/backup 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.deploy_dir}/backup" if $?.exitstatus != 0

          HostManager.log "Unzipping #{PHPApplication.deploy_dir}/application.zip to #{PHPApplication.deploy_dir}/application"
          output = `/usr/bin/unzip -o #{PHPApplication.deploy_dir}/application.zip -d #{PHPApplication.deploy_dir}/application 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to unzip #{PHPApplication.deploy_dir}/application.zip" if $?.exitstatus != 0

          HostManager.log "Re-building #{PHPApplication.web_root_dir}"
          output = `/usr/bin/sudo /bin/rm -Rf #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          output = `/usr/bin/sudo /bin/mkdir -p #{PHPApplication.web_root_dir}/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          # Custom: Install vendors from backup. See above.
          # output = `/usr/bin/sudo /bin/mv #{PHPApplication.backup_dir}/vendor #{PHPApplication.web_root_dir} 2>&1`
          # HostManager.log "Output: #{output}"
          # raise "Failed to install backuped vendors" if $?.exitstatus != 0

          output = `/usr/bin/sudo /bin/chown -Rf elasticbeanstalk:elasticbeanstalk #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set group / owner of #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          output = `/usr/bin/sudo /bin/chmod -Rf 0755 #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set mode of #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          HostManager.log "Moving and adjusting application permissions"
          output = `/usr/bin/sudo /bin/mv -n #{PHPApplication.deploy_dir}/application/{,.}?* #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to move application to #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          output = `/usr/bin/sudo /bin/chown -Rf elasticbeanstalk:elasticbeanstalk #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set owner / group of application deployed to #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          output = `/usr/bin/sudo /bin/chmod -Rf 0755 #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set mode of application deployed to #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          output = `/bin/find #{PHPApplication.web_root_dir} -type f -print0 | /usr/bin/xargs -0 /bin/chmod 0644 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set final mode of application files deployed to #{PHPApplication.web_root_dir}" if $?.exitstatus != 0

          ElasticBeanstalk::HostManager::Utils::BluepillUtil.start_target("httpd") if @is_initialization_phase

          # Custom deployment tasks
          HostManager.log "Start custom deployment tasks"
          
          # setup vendor dir
          HostManager.log "Remove old #{PHPApplication.web_root_dir}/vendor"
          output = `/usr/bin/sudo /bin/rm -Rf #{PHPApplication.web_root_dir}/vendor 2>&1`
          HostManager.log "Output: #{output}"
          
          output = `/usr/bin/sudo /bin/mkdir -p #{PHPApplication.vendor_dir}/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.vendor_dir}" if $?.exitstatus != 0
          
          output = `/usr/bin/sudo /bin/ln -sf #{PHPApplication.vendor_dir}/ #{PHPApplication.web_root_dir}/vendor 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to create #{PHPApplication.vendor_dir} symlink to #{PHPApplication.web_root_dir}/vendor" if $?.exitstatus != 0
          
          # get composer
          output = `/usr/bin/sudo /usr/bin/curl -s https://getcomposer.org/installer | /usr/bin/php  -- --install-dir=#{PHPApplication.web_root_dir} --quiet 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to install composer" if $?.exitstatus != 0
          
          # install vendor
          output = `/usr/bin/sudo /usr/bin/php #{PHPApplication.web_root_dir}/composer.phar install --working-dir=#{PHPApplication.web_root_dir} --no-interaction 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to install vendors" if $?.exitstatus != 0
          
           # get parameters_prod.yml
          awskey    = ElasticBeanstalk::HostManager.config.application['Environment Properties']['AWS_ACCESS_KEY_ID']
          awssecret = ElasticBeanstalk::HostManager.config.application['Environment Properties']['AWS_SECRET_KEY']
          awsbucket = ElasticBeanstalk::HostManager.config.application['Environment Properties']['PARAM1']
          
          output = `/usr/bin/sudo /usr/bin/php -f #{PHPApplication.web_root_dir}/app/Resources/aws/gets3parameters.php "#{awskey}" "#{awssecret}" "#{awsbucket}" "#{PHPApplication.web_root_dir}" 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to retreive parameters_prod.yml from s3 check elbs paramerters" if $?.exitstatus != 0
          
          # Data ICU for version 4.2
          if !File.directory?("#{PHPApplication.web_root_dir}/vendor/symfony/symfony/src/Symfony/Component/Locale/Resources/data/4.2")
            HostManager.log "build ICU 4.2"
            output = `/usr/bin/sudo /usr/bin/php #{PHPApplication.web_root_dir}/vendor/symfony/symfony/src/Symfony/Component/Locale/Resources/data/build-data.php 4.2 2>&1`
            HostManager.log "Output: #{output}"
            raise "Failed to build ICU data for 4.2 version" if $?.exitstatus != 0
          elsif
            HostManager.log "no need to build ICU 4.2"
          end
          
          # Build Propel Model
          output = `/usr/bin/sudo /usr/bin/php #{PHPApplication.web_root_dir}/app/console propel:model:build --quiet 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to build Propel Model" if $?.exitstatus != 0
          
          # Assetic dump 
          #output = `/usr/bin/sudo /usr/bin/php #{PHPApplication.web_root_dir}/app/console assetic:dump --env=app_prod --no-debug --quiet 2>&1`
          #HostManager.log "Output: #{output}"
          #raise "Failed to dump assetic" if $?.exitstatus != 0
          
          #fix files / directories owner / mode
          output = `/usr/bin/sudo /bin/chown -Rf elasticbeanstalk:elasticbeanstalk #{PHPApplication.web_root_dir} 2>&1`
          HostManager.log "Output: #{output}"
          raise "Unable to set owner / group of application deployed to #{PHPApplication.web_root_dir}" if $?.exitstatus != 0
          
          # Create directories 
          output = `/usr/bin/sudo /bin/mkdir -p #{PHPApplication.web_root_dir}/app/logs/ #{PHPApplication.web_root_dir}/app/cache/ #{PHPApplication.web_root_dir}/app/data/ #{PHPApplication.web_root_dir}/app/data/resources/ #{PHPApplication.web_root_dir}/web/uploads/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to create directories" if $?.exitstatus != 0
          
          # Set directory permissions 
          output = `/usr/bin/sudo setfacl -R -m u:ec2-user:rwx #{PHPApplication.web_root_dir}/app/logs/ #{PHPApplication.web_root_dir}/app/cache/ #{PHPApplication.web_root_dir}/app/data/ #{PHPApplication.web_root_dir}/web/uploads/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to set directory permissions 1" if $?.exitstatus != 0
          output = `/usr/bin/sudo setfacl -R -m u:elasticbeanstalk:rwx #{PHPApplication.web_root_dir}/app/logs/ #{PHPApplication.web_root_dir}/app/cache/ #{PHPApplication.web_root_dir}/app/data/ #{PHPApplication.web_root_dir}/web/uploads/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to set directory permissions 2" if $?.exitstatus != 0
          output = `/usr/bin/sudo setfacl -dR -m u:ec2-user:rwx #{PHPApplication.web_root_dir}/app/logs/ #{PHPApplication.web_root_dir}/app/cache/ #{PHPApplication.web_root_dir}/app/data/ #{PHPApplication.web_root_dir}/web/uploads/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to set directory permissions 3" if $?.exitstatus != 0
          output = `/usr/bin/sudo setfacl -dR -m u:elasticbeanstalk:rwx #{PHPApplication.web_root_dir}/app/logs/ #{PHPApplication.web_root_dir}/app/cache/ #{PHPApplication.web_root_dir}/app/data/ #{PHPApplication.web_root_dir}/web/uploads/ 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to set directory permissions 4" if $?.exitstatus != 0
          
          # Clear the cache prod
          output = `/usr/bin/sudo /usr/bin/php #{PHPApplication.web_root_dir}/app/console cache:clear --env="app_prod" 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed to clear the prod cache" if $?.exitstatus != 0
          
          # Execute custom php script for additional deplayoment tasks. This enables you to add some tasks w/o the need to create a new AMI every time.
          output = `/usr/bin/sudo /usr/bin/php -f #{PHPApplication.web_root_dir}/app/Resources/aws/elbs_deploy_extra.php 2>&1`
          HostManager.log "Output: #{output}"
          raise "Some error occured during iexecution of custom elbs_deploy_extra.php script" if $?.exitstatus != 0
          
          HostManager.log "End custom deployment tasks"
          # End custom deployment hook
	  
        rescue
          HostManager.log("Version #{@version_info.version} DEPLOYMENT FAILED: #{$!}\n#{$@.join('\n')}")
          ex = ElasticBeanstalk::HostManager::DeployException.new("Version #{@version_info.version} deployment failed: #{$!}")
          ex.output = output || ''
          raise ex
        end

        def post_deploy
          HostManager.log "Starting post-deployment."
          
          # force apache to reload
          output = `/usr/bin/sudo /usr/bin/sudo /etc/init.d/httpd reload 2>&1`
          HostManager.log "Output: #{output}"
          raise "Failed reload apache server" if $?.exitstatus != 0
        end

      end # PHPApplication class

    end
  end
end