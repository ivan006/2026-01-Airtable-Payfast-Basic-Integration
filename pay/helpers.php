<?php

function readConfig($url)
{
  $configFile = __DIR__ . '/config.json';

  if (!file_exists($configFile)) {
    return null;
  }

  $configs = json_decode(file_get_contents($configFile), true);
  $host = parse_url($url, PHP_URL_HOST);

  return isset($configs[$host]) ? $configs[$host] : null;
}



function generateSignature($data, $passPhrase = null)
{
  // Create parameter string
  $pfOutput = '';
  foreach ($data as $key => $val) {
    if ($val !== '') {
      $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
    }
  }
  // Remove last ampersand
  $getString = substr($pfOutput, 0, -1);
  if ($passPhrase !== null) {
    $getString .= '&passphrase=' . urlencode(trim($passPhrase));
  }
  return md5($getString);
}


function pfValidSignature( $pfData, $pfParamString, $pfPassphrase = null ) {
    // Calculate security signature
    if($pfPassphrase === null) {
        $tempParamString = $pfParamString;
    } else {
        $tempParamString = $pfParamString.'&passphrase='.urlencode( $pfPassphrase );
    }

    $signature = md5( $tempParamString );
    return ( $pfData['signature'] === $signature );
} 