<?php

namespace Tests\Formats;

use Done\Subtitles\Code\Converters\SbvConverter;
use Done\Subtitles\Code\Helpers;
use Done\Subtitles\Subtitles;
use PHPUnit\Framework\TestCase;
use Helpers\AdditionalAssertionsTrait;

class SbvTest extends TestCase {

    use AdditionalAssertionsTrait;

    public function testRecognizesSbv()
    {
        $content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.
TEXT;
        $converter = Helpers::getConverterByFileContent($content);
        $this->assertTrue($converter::class === SbvConverter::class);
        $this->assertTrue(true);
    }

    public function testFileToInternalFormat()
    {
        $actual_file_content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;
        $actual_internal_format = Subtitles::loadFromString($actual_file_content, 'sbv')->getInternalFormat();
        $expected_internal_format = [[
            'start' => 340,
            'end' => 346,
            'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
        ], [
            'start' => 346,
            'end' => 351,
            'lines' => ['Because every child in our society is', 'a part of that society'],
        ]];

        $this->assertInternalFormatsEqual($expected_internal_format, $actual_internal_format);
    }



    public function testConvertToFile()
    {
        $actual_internal_format = [[
            'start' => 340,
            'end' => 346,
            'lines' => ['Don’t think that you can just ignore them', 'because they’re not your children or relatives.'],
        ], [
            'start' => 346,
            'end' => 351,
            'lines' => ['Because every child in our society is', 'a part of that society'],
        ]];
        $expected_file_content = <<< TEXT
0:05:40.000,0:05:46.000
Don’t think that you can just ignore them
because they’re not your children or relatives.

0:05:46.000,0:05:51.000
Because every child in our society is
a part of that society
TEXT;
        $expected_file_content = str_replace("\r", '', $expected_file_content);

        $actual_file_content = (new Subtitles())->setInternalFormat($actual_internal_format)->content('sbv');

        $this->assertEquals($expected_file_content, $actual_file_content);
    }

    // @TODO test time above 1 hour

}