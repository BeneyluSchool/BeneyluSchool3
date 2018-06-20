<?php
namespace BNS\App\MainBundle\Controller;

use BNS\App\MessagingBundle\Model\MessagingConversationPeer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;


class RacvisionController extends Controller
{
    public function getRacvisionXMLAction()
    {
        // Test MySQL
        $dbState = "CRIT";
        try {
            $con = \Propel::getConnection(MessagingConversationPeer::DATABASE_NAME);
            $query = "SELECT 1 + 1 as 'val' ";

            $stmt = $con->prepare($query);
            $stmt->execute();
            $success = false;
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $success = ($row['val'] ?? false) === '2';
            }
            if ($success) {
                $dbState = "OK";
            }
        } catch (\Exception $e) {}

        // Test Redis
        $redisState = "CRIT";
        try{
            $session = $this->get('snc_redis.session');
            $default = $this->get('snc_redis.default');
            if ('PONG' === (string)$session->ping() && 'PONG' === (string)$default->ping()) {
                $redisState = "OK";
            }
        } catch (\Exception $e) {}

        // Test Rabbit
        $rabbitMQState = 'CRIT';
        try {
            $connection = $this->get('old_sound_rabbit_mq.connection.default');
            // force connection
            $connection->reconnect();
            if ($connection->isConnected()) {
                $rabbitMQState = "OK";
            }

        } catch (\Exception $e) {}

        // Test Auth access
        $authState = 'CRIT';
        try {
            $buzz = $this->get('buzz');
            /** @var \Buzz\Message\Response $response */
            $response = $buzz->get($this->getParameter('oauth_host'). '/login');
            if ($response->isSuccessful()) {
                $authState = 'OK';
            }
        } catch (\Exception $e) {}

        // Test Paas access
        $paasState = 'CRIT';
        try {
            $buzz = $this->get('buzz');

            $url = $this->getParameter('paas_url') . sprintf('/api/sms-credit/cost.json');
            $signedUrl = $this->get('bns.paas.security_manager')->signUrl('POST', $url, ['country' => 'FR']);

            $data = ['form' => [
                'message' => 'Hey test message',
                'phone_numbers' => ['+33687654321']
            ]];

            /** @var \Buzz\Message\Response $response */
            $response = $buzz->post($signedUrl, [
                "Content-Type: application/json; charset=utf-8",
            ], json_encode($data));
            if ($response->isSuccessful()) {
                $paasState = 'OK';
            }
        } catch (\Exception $e) {}

        $response = new Response(null, 200, [
            'Content-Type' => 'xml'
        ]);

        return $this->render('BNSAppMainBundle:Racvision:index.xml.twig', [
            'name' => $this->getParameter('beneylu_brand_name'),
            'dbState' => $dbState,
            'redisState' => $redisState,
            'rabbitMQState' => $rabbitMQState,
            'authState' => $authState,
            'paasState' => $paasState,
        ], $response);
    }
}
