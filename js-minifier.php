<?php
if (!is_array($argv) || ($i = count($argv)) < 3) { // 3 = 2 arguments + 0th full line
    echo <<<END
Usage: js-minifier.php [filename] [output filename (optionally)]

END;
    exit;
}

    // setup the URL and read the JS from a file
    $js = file_get_contents($argv[1]);

    // init the request, set various options, and send it
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.toptal.com/developers/javascript-minifier/raw',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([ 'input' => $js ]),

        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $minified = curl_exec($ch);

    echo curl_error($ch);

    // finally, close the request
    curl_close($ch);

    // output the $minified JavaScript
    if ($argv[2])
        file_put_contents($argv[2], $minified);
    else
        echo $minified;
