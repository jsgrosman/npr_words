<?php

require "../vendor/autoload.php";

use NPRWords\provider\CMSProvider;
use NPRWords\provider\WordDbProvider;
use NPRWords\parse\TranscriptParser;

$cms = new CMSProvider();
$wordsDB = new WordDbProvider();

$stories = $cms->getStoriesWithTranscripts($argv[2], $argv[1]);

foreach ($stories as $story)
{
    $storyId = $story['thing_id'];
    $date = $story['thing_publish_date'];
    $url = $story['thing_alt_page_url'];

    if (!$wordsDB->checkStory($storyId)) {

        $dataFile = '../data/' . $date . '/' . $storyId . '.json';
        if (file_exists($dataFile)) {
            $words = json_decode(file_get_contents($dataFile));
        } else {
            $transcriptParser = new TranscriptParser($storyId);

            $words = $transcriptParser->getTranscriptWords();

            if (!file_exists('../data/' . $date)) {
                mkdir('../data/' . $date);
            }

            file_put_contents($dataFile, json_encode($words));
        }

        print $storyId . "," . $date . "," . $url . "\n";

        $chunks = array_chunk($words, 50);

        foreach ($chunks as $someWords) {
            $wordsDB->saveWords($someWords, $date, $storyId, $url);
        }
        $wordsDB->markStory($storyId);
    }

}

echo "\x07";
echo "\x07";
echo "\x07";
echo "\x07";
echo "\x07";
echo "\x07";
echo "\x07";
echo "\x07";
