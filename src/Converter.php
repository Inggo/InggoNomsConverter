<?php

namespace Inggo\Noms;

use Hyn\Frontmatter\Parser;
use cebe\markdown\Markdown;
use Hyn\Frontmatter\Frontmatters\TomlFrontmatter;

class Converter
{
    private $parser;

    public $directory = "";
    private $outputDirectory;
    private $imageDirectory;

    public $files = [];

    public function __construct($directory, $outputDirectory = "out", $imageDirectory = "img")
    {
        $this->parser = new Parser(new Markdown);
        $this->parser->setFrontmatter(TomlFrontmatter::class);

        $this->directory = $directory;
        $this->outputDirectory = $outputDirectory;
        $this->imageDirectory = $imageDirectory;
    }

    public function getFiles($force = false)
    {
        if (!$force && !empty($this->files)) {
            return $this->files;
        }

        $this->files = [];
        $files = scandir($this->directory);

        foreach ($files as $file) {
            $filepath = $this->directory . '/' . $file;
            if (filetype($filepath) == "file") {
                $this->files[] = $filepath;
            }
        }

        return $this->files;
    }

    public function setFiles($files)
    {
        $this->files = $files;
    }

    public function parseFiles()
    {
        foreach ($this->getFiles() as $file) {
            $out = $this->getOutputFile($file);
            if (file_exists($out)) {
                echo 'Skipping ' . $out . ", already exists...\n";
                continue;
            }
            echo 'Processing ' . $file . "...\n";
            $parsed = new ParsedFile($this->parseFile($file), $this->imageDirectory);
            $parsed->dump($out);
        }
    }

    private function getOutputFile($file)
    {
        return $this->outputDirectory . '/' . basename($file);
    }

    public function parseFile($file)
    {
        return $this->parser->parse(file_get_contents($file));
    }
}