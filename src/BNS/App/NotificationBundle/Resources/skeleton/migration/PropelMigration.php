<?php

/**
 * Data object containing the SQL and PHP code to migrate the database
 * up to version {{ time }}.
 * Generated on {{ fullTime }} 
 * 
 * -------------------------------------------------------------------
 * 
 * Add a new notification type with these parameters :
 *  Bundle : {{ bundleName }}
 *  Notification name : {{ notificationUniqueName }}
 *  Is correction : {{ isCorrection }}
 *  Disabled engine : {{ disabledEngines }}
 */

class PropelMigration_{{ time }}
{
    public function preUp($manager)
    {
        // add the pre-migration code here
    }

    public function postUp($manager)
    {
        // add the post-migration code here
    }

    public function preDown($manager)
    {
        // add the pre-migration code here
    }

    public function postDown($manager)
    {
        // add the post-migration code here
    }

    /**
     * Get the SQL statements for the Up migration
     *
     * @return array list of the SQL strings to execute for the Up migration
     *               the keys being the datasources
     */
    public function getUpSQL()
    {
        return array (
  'app' => "
INSERT INTO `notification_type` VALUES ('{{ bundleName }}', '{{ notificationUniqueName }}', '{{ isCorrection }}', {{ disabledEngines }});
",
);
    }

    /**
     * Get the SQL statements for the Down migration
     *
     * @return array list of the SQL strings to execute for the Down migration
     *               the keys being the datasources
     */
    public function getDownSQL()
    {
        return array (
  'app' => "
DELETE FROM `notification_type` WHERE unique_name = '{{ notificationUniqueName }}';
",
);
    }
}