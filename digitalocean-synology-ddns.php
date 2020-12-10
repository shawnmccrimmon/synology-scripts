#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php
/*
Based on namecheap script by hwkr
https://gist.github.com/hwkr/906685a75af55714a2b696bc37a0830a
Usage Instructions ( Obviously your domain has to be hosted on Digital Ocean )
1) Copy this file to /usr/syno/bin/ddns/digitalocean.php
2) Add the following entry in /etc.defaults/ddns_provider.conf
[Custom - DigitalOcean]
        modulepath=/usr/syno/bin/ddns/digitalocean.php
        queryurl=api.digitalocean.com
3) In Synology External Access > DDNS
Hostname = subdomain.domain.com OR domain.com 
Username = put-random-string-here-for-validation-purpose
Password = Digital Ocean api key
*/

if ($argc !== 5) {
    echo 'badparam';
    exit();
}

$account = (string)$argv[1];
$pwd = (string)$argv[2];
$hostname = (string)$argv[3];
$ip = (string)$argv[4];

// check the hostname contains '.'
if (strpos($hostname, '.') === false) {
    echo 'badparam';
    exit();
}

// only for IPv4 format
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo "badparam";
    exit();
}

$array = explode('.', $hostname);
if (count($array) >= 3) {
    $domain = implode('.', array_slice($array, 1));
    $hostname = implode('.', array_slice($array, 0, 1));
} else {
    $domain = implode('.', $array);
    $hostname = '@';
}

$authorization = "Authorization: Bearer ".$pwd;

$listURL = "https://api.digitalocean.com/v2/domains/".$domain."/records";
$listReg = curl_init();
curl_setopt($listReg, CURLOPT_URL, $listURL);
curl_setopt($listReg, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization )); // Inject the token into the header
curl_setopt($listReg, CURLOPT_RETURNTRANSFER, true);
curl_setopt($listReg, CURLOPT_FOLLOWLOCATION, 1);
$listResult = curl_exec($listReg);
curl_close($listReg); // Close the cURL connection
$listResultObj = json_decode($listResult); // Return the received data

// check if we got some records back
if(!property_exists($listResultObj, "domain_records"))
{
    if(property_exists($listResultObj,"message"))
    {
        if(strcmp($listResultObj->message,"Unable to authenticate you")==0)
        {
            echo "badauth";
        }
        elseif(strcmp($listResultObj->message,"Resource not found")==0)
        {
            echo "nohost";
        }
        else 
        {
            echo "911 [List Recources: ".$listResultObj->message."]";
        }
    }
    else
    {
        echo "911 [Unknown error getting domain records list]";
    }
    
    exit();
}

$id = null;
$currentIP = "";
$post = null;

foreach($listResultObj->domain_records as $record)
{
    if(strcmp($record->type,"A")==0 && strcmp($record->name,$hostname)==0)
    {
        $id = $record->id;
        $currentIP = $record->data;
        break;
    }
}

// return error if no A record found
if($id==null)
{
    echo "nohost";
    exit();
}

// return success if IP doesn't need to be updated
if(trim($ip) == trim($currentIP))
{
   echo "good";
   exit();
}

// create the post object to update the record
//$putData = json_encode ( array( 'type' => 'A', 'name' => $hostname, 'data' => "'".$ip."'" ) );
$putData = '{"data":"' . $ip . '"}';

// now update the IP record
$url = "https://api.digitalocean.com/v2/domains/".$domain."/records/".$id;
$reg = curl_init();

print_r($putData);

curl_setopt($reg, CURLOPT_URL, $url);
curl_setopt($reg, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization )); // Inject the token into the header
curl_setopt($reg, CURLOPT_RETURNTRANSFER, true);
curl_setopt($reg, CURLOPT_FOLLOWLOCATION, 1); // This will follow any redirects
curl_setopt($reg, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($reg, CURLOPT_POSTFIELDS,$putData);
$result = curl_exec($reg); // Execute the cURL statement

// TODO: return error if curl fails
$resultObj = json_decode($result);

if(!property_exists($resultObj, "domain_record"))
{
    if(property_exists($resultObj,"message"))
    {
        echo "911 [Update Record: ".$resultObj->message."]";
    }
    else
    {
        echo "911 [Unknown error updating record]";
    }
    exit();
}

echo "good";

?>
