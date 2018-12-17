<?php
namespace NPRWords\parse;

use LanguageDetection\Language;
use NPRWords\provider\CMSProvider;

class TranscriptParser
{
    /** @var int */
    private $storyId;

    private $stopWords =
        ['a','about','above','after','again','against','all',
           'am','an','and','any','are','aren\'t','as','at','be','because','been',
           'before','being','below','between','both','but','by','can\'t','cannot',
           'could','couldn\'t','did','didn\'t','do','does','doesn\'t','doing','don\'t',
           'down','during','each','few','for','from','further','had','hadn\'t',
           'has','hasn\'t','have','haven\'t','having','he','he','d','he\'ll','he\'s',
           'her','here','here','s','hers','herself','him','himself','his','how','how\'s',
           'i','i\'d','i\'ll','i\'m','i\'ve','if','in','into','is','isn\'t','it','it\'s',
           'its','itself','let\'s','me','more','most','mustn\'t','my','myself','no',
           'nor','not','of','off','on','once','only','or','other','ought','our','ours',
           'ourselves','out','over','own','same','shan\'t','she','she\'d','she\'ll',
           'she\'s','should','shouldn\'t','so','some','such','than','that','that\'s',
           'the','their','theirs','them','themselves','then','there','there\'s',
           'these','they','they\'d','they\'ll','they\'re','they\'ve','this','those',
           'through','to','too','under','until','up','very','was','wasn\'t','we',
           'we\'d','we\'ll','we\'re','we\'ve','were','weren\'t','what','what\'s',
           'when','when\'s','where','where\'s','which','while','who','who\'s',
           'whom','why','why\'s','with','won\'t','would','wouldn\'t','you',
           'you\'d','you\'ll','you\'re','you\'ve','your','yours','yourself','yourselves'
    ];

    public function __construct($storyId)
    {
        $this->storyId = $storyId;
    }


    public function getTranscriptWords()
    {
        $ld = new Language();
        $cms = new CMSProvider();

        $paras = $cms->getTranscript($this->storyId);

        $finalWordList = [];

        foreach ($paras as $para)
        {
            if (strpos($para, 'Copyright') === 0)
            {
                continue;
            }

            if (strpos($para, 'NPR transcripts') === 0)
            {
                continue;
            }

            $words =  preg_split('/\s+/', $para);

            $words = array_map(function($str) {
                return trim(strtolower(preg_replace('/[^a-z\']+/i', ' ', $str)));
            }, $words);

            // remove all upper, and non-english
            $words = array_filter($words, function ($str) use ($ld) {

                if (strtoupper($str) === $str)
                {
                    return false;
                }

                if (in_array($str, $this->stopWords))
                {
                    return false;
                }

                $lang = $ld->detect($str)->whitelist(
                    'zh-Hans',
                    'es',
                    'en',
                    'ar',
                    'hi',
                    'ur',
                    'bn',
                    'pt-PT',
                    'pt-BR',
                    'ru',
                    'ja',
                    'fr',
                    'de')->close();


                // print($str . '=' . $lang['en'] . ": " . $ld->detect($str)->__toString() . "\n");

                if ($lang['en'] < .30)
                {
                    return false;
                }

                return true;
            });



            foreach ($words as $word) {
                if (!in_array($word, $finalWordList)) {
                    array_push($finalWordList, $word);
                }
            }
        }

        sort($finalWordList);

        return $finalWordList;
    }


}
