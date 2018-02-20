<?php

namespace Inggo\Noms;

use DOMDocument;
use Cocur\Slugify\Slugify;

class ParsedFile
{
    public $title;
    public $slug;
    public $html;
    public $markdown;
    public $meta;
    public $document;

    public $images = [];

    private $slugifier;
    private $imageDirectory = "img";
    private $imageUri = "/img";

    public function dump($file = null)
    {
        ob_start();
        echo "+++\n";
        echo "title = \"{$this->meta['title']}\"\n";
        echo "subtitle = \"{$this->meta['subtitle']}\"\n";
        echo "slug = \"{$this->meta['slug']}\"\n";
        echo "date = {$this->meta['date']->format('Y-m-d\TH:i:s\Z')}\n";
        echo "updated = {$this->meta['updated']->format('Y-m-d\TH:i:s\Z')}\n";
        if (count($this->meta['tags'])) {
            echo "tags = [";
            echo '"' . implode('", "', $this->meta['tags']) . '"';
            echo "]\n";
        }
        echo "rating = \"{$this->meta['rating']}\"\n";
        echo "stars = \"{$this->meta['stars']}\"\n";
        echo "location = \"{$this->meta['location']}\"\n";
        if (array_key_exists('map', $this->meta) && $this->meta['map']) {
            echo "map = \"{$this->meta['map']}\"\n";
        }
        echo "budget = \"{$this->meta['budget']}\"\n";
        echo "reco = \"{$this->meta['reco']}\"\n";
        echo "notreco = \"{$this->meta['notreco']}\"\n";
        echo "tip = \"{$this->meta['tip']}\"\n";
        echo "mediaimg = \"{$this->meta['mediaimg']}\"\n";
        echo "headerbg = \"{$this->meta['headerbg']}\"\n";
        echo "asidebg = \"{$this->meta['asidebg']}\"\n";
        echo "[author]\n";
        echo "\tname = \"{$this->meta['author']['name']}\"\n";
        echo "\turi = \"{$this->meta['author']['uri']}\"\n";
        echo "+++\n\n";
        echo $this->markdown;
        if ($file) {
            file_put_contents($file, ob_get_contents());
        }
        ob_end_clean();
    }

    public function __construct($parsedFile, $imageDirectory = "img", $imageUri = "/img")
    {
        $this->imageDirectory = $imageDirectory;
        $this->imageUri = $imageUri;
        $this->slugifier = new Slugify();
        $this->meta = $this->cleanMeta($parsedFile['meta']);
        $this->html = $this->cleanHtml($parsedFile['markdown']);
        $this->markdown = $this->html;
        $this->title = $this->setTitle();
        $this->replaceImages();
    }

    private function setTitle()
    {
        $this->title = $this->meta['title'];
        if ($this->hasSubtitle()) {
            $this->title .= ' ' . $this->meta['subtitle'];
        }
        $this->slug = $this->slugifier->slugify($this->title);
        $this->meta['slug'] = $this->slug;
    }

    private function hasSubtitle()
    {
        return array_key_exists('subtitle', $this->meta) && $this->meta['subtitle'];
    }

    public function cleanHtml($html)
    {
        $html = preg_replace('/^\s*<p>(.*)<\/p>\s*$/', '$1', $html);
        $replace = [
            '<b> </b>' => ' ',
            '<i> </i>' => ' ',
            ' </b>' => '</b> ',
            ' </i>' => '</i> ',
            '?' => '&mdash;',
        ];

        $html = str_replace(array_keys($replace), $replace, $html);
        // Run twice for possible double-wraps
        $html = str_replace(array_keys($replace), $replace, $html);

        $html = $this->cleanTags($html);

        $html = $this->parseMeta($html);

        $html = $this->cleanImages($html);

        if (count($this->images) === 0) {
            $html = $this->processImages($html);
        }

        $html = $this->cleanup($html);

        return $html;
    }

    private function processImages($html)
    {
        preg_match_all('/!\[(.*)\]\((.*)\)/', $html, $matches, PREG_SET_ORDER, 0);

        foreach ($matches as $match) {
            $this->images[] = [
                'alt' => $match[1],
                'uri' => $match[2]
            ];
        }

        $html = preg_match_all('/!\[.*\]\((.*)\)/', '$2', $html);

        return $html;
    }

    private function cleanup($html)
    {
        $replace = [
            "<html><body>\n<p>" => '',
            "<br><br>\n</html></body>" => '',
            "<br><br></p>" => '<br><br>',
            "<html>" => '',
            "<body>" => '',
            "</html>" => '',
            "</body>" => '',
            "<i>" => "*",
            "</i>" => "*",
            "<b>" => "**",
            "</b>" => "**",
            "<br>" => "\n",
            "<a name=\"more\"></a>" => "\n<!--more-->\n",
            "<p>" => "\n",
            "</p>" => "\n",
        ];

        $html = str_replace(array_keys($replace), $replace, $html);

        $html = preg_replace('/<a href="(.*)">(.*)<\/a>/', '[$2]($1)', $html);

        $html = preg_replace('/\n{3,}/', "\n", $html);

        return trim($html);
    }

    public function cleanImages($html)
    {
        $dom = new DOMDocument;
        $dom->loadHTML($html);

        $imgs = $dom->getElementsByTagName('img');

        for ($i = $imgs->length - 1; $i > -1; $i--) {
            $img = $imgs->item($i);
            $image = $img->parentNode->getAttribute('href');
            $this->images[] = $image;
            $img->parentNode->parentNode->replaceChild($dom->createTextNode($image . "\n"), $img->parentNode);
        }

        return $dom->saveHTML($dom->documentElement);
    }

    public function cleanMeta($meta)
    {
        preg_match_all('/(.+) \((.+)\)/', $meta['title'], $matches, PREG_SET_ORDER, 0);

        if (count($matches) == 1) {
            $meta['title'] = $matches[0][1];
            $meta['subtitle'] = $matches[0][2];
        }

        unset($this->meta['blogimport']);

        return $meta;
    }

    private function cleanTags($html)
    {
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        $divs = $dom->getElementsByTagName('div');

        for ($i = $divs->length - 1; $i > -1; $i--) {
            $div = $divs->item($i);
            if ($div->hasChildNodes()) {
                $image = $div->firstChild->getAttribute('href');
                $this->images[] = $image;
                $div->parentNode->replaceChild($dom->createTextNode($image . "\n"), $div);
            } else {
                $div->parentNode->removeChild($div);
            }
        }

        return $dom->saveHTML($dom->documentElement);
    }

    private function parseMeta($html)
    {
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

        // Find rating table
        $tables = $dom->getElementsByTagName('table');

        for ($i = $tables->length - 1; $i > -1; $i--) {
            $table = $tables->item($i);
                             // tbody    // tr       // td       // a
            $thumbs = $table->firstChild->firstChild->firstChild->firstChild->getAttribute('href');
            if (!$thumbs) {
                $thumbs = $table->firstChild->firstChild->firstChild->firstChild->getAttribute('src');
            }
            $stars = $this->convertStars($thumbs);
            $rating = $table->firstChild->lastChild->firstChild->textContent;

            // Remove the table
            $table->parentNode->removeChild($table);
        }

        $html = $dom->saveHTML($dom->documentElement);

        if ($stars) {
            $this->meta['stars'] = $stars;
        }
        if ($rating) {
            $this->meta['rating'] = $rating;
        }

        return $this->parseTldr($html);
    }

    private function parseTldr($html)
    {
        $re = '/(?:<span style=".*">)??<span style=".*"> ?tl;dr(?:<\/span>)??<\/span><br>(?:<span style=".*"><span style=".*"><\/span><\/span>)?<span style=".*">Location: ?(?:<\/span>(?:<span style=".*"><\/span>)?<br>|<br><\/span>)(?:<span style=".*"><\/span>)?(.*)<br><span style=".*">Budget: ?(?:<br><\/span>|<\/span><br>)(.*)<br><span style=".*">Recommended\s+?for: ?(?:<br><\/span>|<\/span><br>)(.*)<br><span style=".*">Not\s+?(?:r|R)ecommended  ?for: ?(?:<br><\/span>|<\/span>(?:<span style=".*"><\/span>)?<br>)(.*)<br><span style=".*">(.*)(?:<\/span><br>|<br><\/span>)(.*)/';

        preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);

        $html = preg_replace($re, "", $html);

        if (count($matches)) {
            $dom = new DOMDocument();
            $dom->loadHTML($matches[0][1]);
            $a = $dom->getElementsByTagName('a');
            if ($a->length) {
                $link = $a->item(0);
                $this->meta['map'] = $link->getAttribute('href');
                $this->meta['location'] = $link->textContent;
            } else {
                $this->meta['location'] = $matches[0][1];
            }

            $this->meta['budget'] = $matches[0][2];
            $this->meta['reco'] = $matches[0][3];
            $this->meta['notreco'] = $matches[0][4];

            $this->meta['tip'] = $matches[0][6];
            
            if ($matches[0][5] != 'Smart nomnomnom tip:') {
                $this->meta['tipoverride'] = $matches[0][5];
            }
        }

        return $html;
    }

    private function convertStars($thumbs)
    {
        if (preg_match('/UP3\.png$/', $thumbs)) {
            return 5;
        }

        if (preg_match('/UP2\.png$/', $thumbs)) {
            return 4;
        }

        if (preg_match('/UP\.png$/', $thumbs)) {
            return 3;
        }

        if (preg_match('/DN\.png$/', $thumbs)) {
            return 2;
        }

        if (preg_match('/DN2\.png$/', $thumbs)) {
            return 1;
        }

        if (preg_match('/DN3\.png$/', $thumbs)) {
            return 0;
        }

        return -1;
    }

    private function replaceImages()
    {
        // Grab current images
        $oldImages = $this->images;

        $this->images = [];

        // Download all images
        foreach ($oldImages as $i => $image) {
            if (is_array($image)) {
                $alt = $image['alt'];
                $image = $image['uri'];

                // Fix URI
                if (preg_match('/^\/\//', $image)) {
                    $image = 'https:' . $image;
                }
            } else {
                $alt = $this->meta['title'];
            }

            $imgFile = '/' . $this->slug . '-' . $i . '.jpg';
            $imgPath = $this->imageDirectory . $imgFile;
            $imgUri = $this->imageUri . $imgFile;

            echo "Downloading {$image} to {$imgPath}...\n";
            
            file_put_contents($imgPath, file_get_contents($image));
            $this->images[] = $imgUri;
            $this->markdown = str_replace($image, '![' . $alt . '](' . $imgUri . ')', $this->markdown);
        }

        // Set image meta
        if (count($this->images)) {
            $this->meta['mediaimg'] = $this->images[0];
            $this->meta['headerbg'] = $this->images[0];
            $this->meta['asidebg'] = $this->images[1 % count($this->images)];
        }
    }
}