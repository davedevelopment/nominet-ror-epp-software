<?php

class Epp
{

    private $connection;

    // Connect to the EPP
    public function connect($address = "testbed-epp.nominet.org.uk", $port = 700)
    {
        $timeout = @ini_get('default_socket_timeout');
        $flags = null;
        $options = null;
        $context = stream_context_create($options);
        $flags = STREAM_CLIENT_CONNECT;

        $this->connection = stream_socket_client($address . ':' . $port, $errno, $errstr, $timeout, $flags, $context);

        if ($this->connection == FALSE) {
            exit("Message: Script failed, likely cause is the recent addition of the IP address to the Nominet test bed due to the large amount of people currently setting up. Please try running the script in 60 minutes time again.\r\n");
        }

        stream_set_blocking($this->connection, (int)false);
        stream_set_write_buffer($this->connection, 0);

        $this->readEPP($this->connection);
    }

    // Login to the EPP
    public function login($tag, $password)
    {
        $loginXML = '<?xml version="1.0" encoding="UTF-8"?>
  <epp xmlns="urn:ietf:params:xml:ns:epp-1.0"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
    <command>
      <login>
        <clID>' . $tag . '</clID>
        <pw>' . $password . '</pw>
        <options>
          <version>1.0</version>
          <lang>en</lang>
        </options>
        <svcs>
           <objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>
           <objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>
           <objURI>urn:ietf:params:xml:ns:host-1.0</objURI>
           <svcExtension>
             <extURI>http://www.nominet.org.uk/epp/xml/contact-nom-ext-1.0</extURI>
             <extURI>http://www.nominet.org.uk/epp/xml/domain-nom-ext-1.0</extURI>
           </svcExtension>
        </svcs>
      </login>
    </command>
  </epp>';

        return $this->sendEPP($loginXML, "Command completed successfully");
    }

    // Create a test contact
    function createContact($registrant, $name, $org)
    {
        $createContactXML = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
    xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
    epp-1.0.xsd\">
    <command>
        <create>
            <contact:create
                xmlns:contact=\"urn:ietf:params:xml:ns:contact-1.0\" 
                xsi:schemaLocation=\"urn:ietf:params:xml:ns:contact-1.0
                contact-1.0.xsd\">
                <contact:id>" . $registrant . "</contact:id>
                <contact:postalInfo type=\"loc\">
                    <contact:name>" . $name . "</contact:name>
                    <contact:org>" . $org . "</contact:org>
                    <contact:addr>
                        <contact:street>Teststreet 1</contact:street>
                        <contact:street>Teststreet 2</contact:street>
                        <contact:city>Oxford</contact:city>
                        <contact:sp>England</contact:sp>
                        <contact:pc>OX1 1AH</contact:pc>
                        <contact:cc>GB</contact:cc>
                    </contact:addr>
                </contact:postalInfo>
                <contact:voice>+44.753035251</contact:voice>
                <contact:email>test@test.com</contact:email>
                <contact:authInfo>
                    <contact:pw></contact:pw>
                </contact:authInfo>
            </contact:create>
        </create>
    </command>
</epp>";

        return $this->sendEPP($createContactXML, "Command completed successfully", 1);
    }

    // Create a domain
    function createDomain($domain, $password, $registrant)
    {
        $createDomainXML = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
   xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
   xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
   epp-1.0.xsd\">
   <command>
     <create>
       <domain:create
         xmlns:domain=\"urn:ietf:params:xml:ns:domain-1.0\"
         xsi:schemaLocation=\"urn:ietf:params:xml:ns:domain-1.0
         domain-1.0.xsd\">
         <domain:name>" . $domain . "</domain:name>
         <domain:registrant>" . $registrant . "</domain:registrant>
         <domain:authInfo>
           <domain:pw>$password</domain:pw>
         </domain:authInfo>
       </domain:create>
     </create>
     <clTRID>abcde12345</clTRID>
   </command>
</epp>";

        return $this->sendEPP($createDomainXML, "Command completed successfully");
    }

    // Multi domain create, with minute based sleeping
    function multiCreate($domains, $password, $registrant, $min)
    {
        $createDomainsXML = null;

        if (count($domains) < 6) {
            $value = count($domains);

            while ($value != 6) {
                $domains[] = $domains[0];
                $value++;
            }
        }

        // Create domain XML for the first six domains on the list.
        foreach ($domains as $domain) {
            $createDomainXML[] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
   xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
   xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
   epp-1.0.xsd\">
   <command>
     <create>
       <domain:create
         xmlns:domain=\"urn:ietf:params:xml:ns:domain-1.0\"
         xsi:schemaLocation=\"urn:ietf:params:xml:ns:domain-1.0
         domain-1.0.xsd\">
         <domain:name>" . $domain . "</domain:name>
         <domain:registrant>" . $registrant . "</domain:registrant>
         <domain:authInfo>
           <domain:pw>$password</domain:pw>
         </domain:authInfo>
       </domain:create>
     </create>
     <clTRID>abcde12345</clTRID>
   </command>
</epp>";
        }

        if ($min == 0) {
            $hour = date("H") + 1;
        } else {
            $hour = date("H");
        }

        // Modify the minute to add a leading zero if single character
        if (strlen($min) < 2) {
            $min = "0" . $min;
        }
        intval($min);

        // Modify the minute to add a leading zero if single character
        if (strlen($hour) < 2) {
            $hour = "0" . $hour;
        }

        echo "Next Run: 2019-06-" . date("d") . " {$hour}:{$min}:00.000\r\n";

        // Calculate the time now and the time to the start of the next minute
        $start_date = new DateTime(date("Y") . "-" . date("m") . "-" . date("d") . " {$hour}:{$min}:00.000");
        $t = microtime(true);
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $now_time = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));

        // Calculate how long to sleep for
        $sleep = number_format(abs((float)$now_time->format("U.u") - (float)$start_date->format("U.u")), 6) * 100;


        $sleep = str_replace(".", "", $sleep);
        $sleep = intval($sleep);
        usleep($sleep);

        // Send EPP requests
        fputs($this->connection, $createDomainXML[0], strlen($createDomainXML[0]));
        fputs($this->connection, $createDomainXML[1], strlen($createDomainXML[1]));
        fputs($this->connection, $createDomainXML[2], strlen($createDomainXML[2]));
        fputs($this->connection, $createDomainXML[3], strlen($createDomainXML[3]));
        fputs($this->connection, $createDomainXML[4], strlen($createDomainXML[4]));
        fputs($this->connection, $createDomainXML[5], strlen($createDomainXML[5]));

        // Read EPP responses to log and return to the minute based loop.
        $this->readEPP($this->connection);

        return;
    }

    // Send EPP request for contact create
    public function sendEPP($data, $find, $log = null)
    {
        fputs($this->connection, $data, strlen($data));

        $response = $this->readEPP($this->connection);

        if ($log) {
            $this->createLock($response);
        }

        $pos = strpos($response, $find);

        if ($pos !== false) {
            return true;
        }

        return false;
    }

    // Read EPP responses
    public function readEPP($connection)
    {
        $buffer = null;
        $time_pre = microtime(true);
        while (!feof($connection)) {
            $buffer .= stream_get_contents($connection, -1);

            $time_post = microtime(true);
            $end_time = $time_post - $time_pre;

            if ($end_time > 5) {
                break;
            }
        }

        $this->logs($buffer, "EPP");

        return $buffer;
    }

    // Creat registrant lock file (for noy running multiple test creates)
    public function createLock($response)
    {
        $handle = fopen("REGISTRANT", 'w') or die('Cannot open file');
        $data = $this->getDataValue($response, "<contact:id>", "</contact:id>");
        fwrite($handle, $data);
        fclose($handle);
    }

    // Get registrant ID from XML create contact
    public function getDataValue($str, $from, $to)
    {
        $sub = substr($str, strpos($str, $from) + strlen($from), strlen($str));
        return substr($sub, 0, strpos($sub, $to));
    }

    // Log to file
    public function logs($msg, $file)
    {
        $date = date("d-m-y");
        $time = $this->udate('H:i:s.u');

        $f = fopen("logs/" . $date . "-" . $file . ".txt", 'a');

        fputs($f, trim($time) . ",{$msg}\n");

        fclose($f);
    }

    // Get millisecond timestamps
    public function udate($format, $utimestamp = null)
    {
        if (is_null($utimestamp))
            $utimestamp = microtime(true);
        $timestamp = floor($utimestamp);
        $milliseconds = round(($utimestamp - $timestamp) * 1000);
        return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
    }
}
