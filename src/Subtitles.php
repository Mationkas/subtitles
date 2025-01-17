<?php

namespace Done\Subtitles;

use Done\Subtitles\Code\Converters\AssConverter;
use Done\Subtitles\Code\Converters\CsvConverter;
use Done\Subtitles\Code\Converters\DfxpConverter;
use Done\Subtitles\Code\Converters\SbvConverter;
use Done\Subtitles\Code\Converters\SccConverter;
use Done\Subtitles\Code\Converters\SmiConverter;
use Done\Subtitles\Code\Converters\SrtConverter;
use Done\Subtitles\Code\Converters\StlConverter;
use Done\Subtitles\Code\Converters\SubMicroDvdConverter;
use Done\Subtitles\Code\Converters\SubViewerConverter;
use Done\Subtitles\Code\Converters\TtmlConverter;
use Done\Subtitles\Code\Converters\TxtConverter;
use Done\Subtitles\Code\Converters\TxtQuickTimeConverter;
use Done\Subtitles\Code\Converters\VttConverter;
use Done\Subtitles\Code\Helpers;

class Subtitles
{
    protected $input;

    protected $internal_format; // data in internal format (when file is converted)

    protected $converter;
    protected $output;

    public static $formats = [
        ['extension' => 'ass',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'ssa',  'format' => 'ass',              'name' => 'Advanced Sub Station Alpha', 'class' => AssConverter::class],
        ['extension' => 'csv',  'format' => 'csv',              'name' => 'Coma Separated Values',      'class' => CsvConverter::class],
        ['extension' => 'dfxp', 'format' => 'dfxp',             'name' => 'Netflix Timed Text',         'class' => DfxpConverter::class],
        ['extension' => 'sbv',  'format' => 'sbv',              'name' => 'YouTube',                    'class' => SbvConverter::class],
        ['extension' => 'srt',  'format' => 'srt',              'name' => 'SubRip',                     'class' => SrtConverter::class],
        ['extension' => 'stl',  'format' => 'stl',              'name' => 'Spruce Subtitle File',       'class' => StlConverter::class],
        ['extension' => 'sub',  'format' => 'sub_microdvd',     'name' => 'MicroDVD',                   'class' => SubMicroDvdConverter::class],
        ['extension' => 'sub',  'format' => 'sub_subviewer',    'name' => 'SubViewer2.0',               'class' => SubViewerConverter::class],
        ['extension' => 'ttml', 'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'xml',  'format' => 'ttml',             'name' => 'TimedText 1.0',              'class' => TtmlConverter::class],
        ['extension' => 'smi',  'format' => 'smi',              'name' => 'SAMI',                       'class' => SmiConverter::class],
        ['extension' => 'txt',  'format' => 'txt',              'name' => 'Plaintext',                  'class' => TxtConverter::class],
        ['extension' => 'txt',  'format' => 'txt_quicktime',    'name' => 'Quick Time Text',            'class' => TxtQuickTimeConverter::class],
        ['extension' => 'vtt',  'format' => 'vtt',              'name' => 'WebVTT',                     'class' => VttConverter::class],
        ['extension' => 'scc',  'format' => 'scc',              'name' => 'Scenarist',                  'class' => SccConverter::class],
    ];

    public static function convert($from_file_path, $to_file_path, $to_format = null)
    {
        static::loadFromFile($from_file_path)->save($to_file_path, $to_format);
    }

    public function save($path, $format = null)
    {
        $file_extension = Helpers::fileExtension($path);
        if ($format === null) {
            $format = $file_extension;
        }
        $content = $this->content($format);

        file_put_contents($path, $content);

        return $this;
    }

    public function add($start, $end, $text)
    {
        // @TODO validation
        // @TODO check subtitles to not overlap
        $this->internal_format[] = [
            'start' => $start,
            'end' => $end,
            'lines' => is_array($text) ? $text : [$text],
        ];

        $this->sortInternalFormat();

        return $this;
    }

    public function trim($startTime, $endTime)
    {
        $this->remove('0', $startTime);
        $this->remove($endTime, $this->maxTime());

        return $this;
    }

    public function remove($from, $till)
    {
        foreach ($this->internal_format as $k => $block) {
            if ($this->shouldBlockBeRemoved($block, $from, $till)) {
                unset($this->internal_format[$k]);
            }
        }

        $this->internal_format = array_values($this->internal_format); // reorder keys

        return $this;
    }

    public function shiftTime($seconds, $from = 0, $till = null)
    {
        foreach ($this->internal_format as &$block) {
            if (!Helpers::shouldBlockTimeBeShifted($from, $till, $block['start'], $block['end'])) {
                continue;
            }

            $block['start'] += $seconds;
            $block['end'] += $seconds;
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function shiftTimeGradually($seconds, $from = 0, $till = null)
    {
        if ($till === null) {
            $till = $this->maxTime();
        }

        foreach ($this->internal_format as &$block) {
            $block = Helpers::shiftBlockTime($block, $seconds, $from, $till);
        }
        unset($block);

        $this->sortInternalFormat();

        return $this;
    }

    public function content($format)
    {
        $converter = Helpers::getConverterByFormat($format);
        $content = $converter->internalFormatToFileContent($this->internal_format);

        return $content;
    }

    // for testing only
    public function getInternalFormat()
    {
        return $this->internal_format;
    }

    // for testing only
    public function setInternalFormat(array $internal_format)
    {
        $this->internal_format = $internal_format;

        return $this;
    }

    // -------------------------------------- private ------------------------------------------------------------------

    protected function sortInternalFormat()
    {
        usort($this->internal_format, function ($item1, $item2) {
            if ($item2['start'] == $item1['start']) {
                return 0;
            } elseif ($item2['start'] < $item1['start']) {
                return 1;
            } else {
                return -1;
            }
        });
    }

    public function maxTime()
    {
        $max_time = 0;
        foreach ($this->internal_format as $block) {
            if ($max_time < $block['end']) {
                $max_time = $block['end'];
            }
        }

        return $max_time;
    }

    protected function shouldBlockBeRemoved($block, $from, $till)
    {
        return ($from < $block['start'] && $block['start'] < $till) || ($from < $block['end'] && $block['end'] < $till);
    }

    public static function loadFromFile($path, $format = null)
    {
        if (!file_exists($path)) {
            throw new \Exception("file doesn't exist: " . $path);
        }

        $string = file_get_contents($path);

        return static::loadFromString($string, $format);
    }

    public static function loadFromString($text, $format = null)
    {
        $converter = new static;
        $converter->input = Helpers::normalizeNewLines(Helpers::removeUtf8Bom($text));

        if ($format) {
            $input_converter = Helpers::getConverterByFormat($format);
        } else {
            $input_converter = Helpers::getConverterByFileContent($converter->input);
        }
        $converter->internal_format = $input_converter->fileContentToInternalFormat($converter->input);

        return $converter;
    }
}