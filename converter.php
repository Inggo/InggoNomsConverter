<?php

require "vendor/autoload.php";

use Inggo\Noms\Converter;

$converter = new Converter("../content/reviews", "../content/converted", "../static/img");

$converter->setFiles([
    "../content/reviews/frostbite-sm-north-edsa.md"
]);

$converter->parseFiles();
