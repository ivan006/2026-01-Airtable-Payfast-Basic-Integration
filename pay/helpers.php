<?php

function readConfig($url) {
  $configFile = __DIR__ . '/config.json';

  if (!file_exists($configFile)) {
    return null;
  }

  $configs = json_decode(file_get_contents($configFile), true);
  $host = parse_url($url, PHP_URL_HOST);

  return isset($configs[$host]) ? $configs[$host] : null;
}



function generateApiSignature($pfData, $passPhrase = null)
{
    if ($passPhrase !== null && $passPhrase !== '') {
        $pfData['passphrase'] = $passPhrase;
    }

    ksort($pfData);

    $pfParamString = http_build_query($pfData, '', '&', PHP_QUERY_RFC1738);

    return md5($pfParamString);
}
