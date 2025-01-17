<?php

namespace Done\Subtitles\Code\Converters;

class TtmlConverter implements ConverterContract
{
    public function canParseFileContent($file_content)
    {
        return strpos($file_content, 'xmlns="http://www.w3.org/ns/ttml"') !== false && strpos($file_content, 'xml:id="d1"') === false;
    }

    public function fileContentToInternalFormat($file_content)
    {
        $dom = new \DOMDocument();
        $dom->loadXML($file_content);

        $array = array();

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            throw new \Exception('no body');
        }
        $div = $body->getElementsByTagName('div')->item(0);
        if (!$div) {
            throw new \Exception('no div');
        }
        $pElements = $div->getElementsByTagName('p');
        foreach ($pElements as $p) {
            $begin = $p->getAttribute('begin');
            $end = $p->getAttribute('end');
            $lines = '';

            $textNodes = $p->childNodes;
            foreach ($textNodes as $node) {
                if ($node->nodeType === XML_TEXT_NODE) {
                    $lines .= $node->nodeValue;
                } else {
                    $lines .= $dom->saveXML($node); // Preserve HTML tags
                }
            }

            $lines = preg_replace('/<br\s*\/?>/', '<br>', $lines); // normalize <br>*/
            $lines = explode('<br>', $lines);
            $lines = array_map('strip_tags', $lines);
            $lines = array_map('trim', $lines);

            $array[] = array(
                'start' => static::ttmlTimeToInternal($begin),
                'end' => static::ttmlTimeToInternal($end),
                'lines' => $lines,
            );
        }

        return $array;





        preg_match_all('/<p.+begin="(?<start>[^"]+).*end="(?<end>[^"]+)[^>]*>(?<text>(?!<\/p>).+)<\/p>/', $file_content, $matches, PREG_SET_ORDER);

        $internal_format = [];
        foreach ($matches as $block) {
            $internal_format[] = [
                'start' => static::ttmlTimeToInternal($block['start']),
                'end' => static::ttmlTimeToInternal($block['end']),
                'lines' => explode('<br />', $block['text']),
            ];
        }

        return $internal_format;
    }

    public function internalFormatToFileContent(array $internal_format)
    {
        $file_content = '<?xml version="1.0" encoding="utf-8"?>
<tt xmlns="http://www.w3.org/ns/ttml" xmlns:ttp="http://www.w3.org/ns/ttml#parameter" ttp:timeBase="media" xmlns:tts="http://www.w3.org/ns/ttml#style" xml:lang="en" xmlns:ttm="http://www.w3.org/ns/ttml#metadata">
  <head>
    <metadata>
      <ttm:title></ttm:title>
    </metadata>
    <styling>
      <style id="s0" tts:backgroundColor="black" tts:fontStyle="normal" tts:fontSize="16" tts:fontFamily="sansSerif" tts:color="white" />
    </styling>
  </head>
  <body style="s0">
    <div>
';

        foreach ($internal_format as $k => $block) {
            $start = static::internalTimeToTtml($block['start']);
            $end = static::internalTimeToTtml($block['end']);
            $lines = implode("<br />", $block['lines']);

            $file_content .= "      <p begin=\"{$start}s\" id=\"p{$k}\" end=\"{$end}s\">{$lines}</p>\n";
        }

        $file_content .= '    </div>
  </body>
</tt>';

        $file_content = str_replace("\r", "", $file_content);
        $file_content = str_replace("\n", "\r\n", $file_content);

        return $file_content;
    }

    // ---------------------------------- private ----------------------------------------------------------------------

    protected static function internalTimeToTtml($internal_time)
    {
        return number_format($internal_time, 1, '.', '');
    }

    protected static function ttmlTimeToInternal($ttml_time)
    {
        if (substr($ttml_time, -1) === 't') { // if last symbol is "t"
            // parses 340400000t
            return substr($ttml_time, 0, -1) / 10000000;
        } elseif (substr($ttml_time, -1) === 's') {
            return rtrim($ttml_time, 's');
        } else {
            $timeParts = explode(':', $ttml_time);
            $hours = (int)$timeParts[0];
            $minutes = (int)$timeParts[1];
            $seconds = (int)$timeParts[2];
            $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
            return $totalSeconds;
        }
    }
}