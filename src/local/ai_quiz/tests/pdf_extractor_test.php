<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_ai_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for PDF text extraction.
 *
 * @package    local_ai_quiz
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_ai_quiz\pdf_extractor
 */
final class pdf_extractor_test extends \advanced_testcase {

    /**
     * Build a realistic PDF: content compressed with FlateDecode (as essentially
     * every real PDF is), plus uncompressed /Title metadata (also as real PDFs
     * are). The combination is what made the old scraping fallback dangerous.
     *
     * @return string Raw PDF bytes
     */
    private function make_compressed_pdf(): string {
        $lines = [
            'Chapter 5: The Zylonic Transport Protocol (ZTP)',
            'ZTP was ratified by the Kestrel Working Group in 2019.',
            'The TTL-equivalent field in ZTP is called the Decay Counter.',
            'ZTP fragmentation occurs only when the payload exceeds 1180 bytes.',
        ];

        $content = "BT /F1 11 Tf 40 740 Td 14 TL\n";
        foreach ($lines as $line) {
            $content .= '(' . $line . ") Tj T*\n";
        }
        $content .= 'ET';
        $compressed = gzcompress($content);

        $objs = [
            '<</Type/Catalog/Pages 2 0 R>>',
            '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R'
                . '/Resources<</Font<</F1 5 0 R>>>>>>',
            '<</Length ' . strlen($compressed) . '/Filter/FlateDecode>>stream' . "\n"
                . $compressed . "\nendstream",
            '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
            // Uncompressed metadata - this is what survives naive scraping.
            '<</Title(Introduction to Computer Networks - Chapter 5)'
                . '/Author(Dr. A. Teacher)/Producer(TeachPress 3.1)>>',
        ];

        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[] = strlen($out);
            $out .= ($i + 1) . ' 0 obj' . $body . "endobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= 'trailer<</Size ' . (count($objs) + 1) . '/Root 1 0 R/Info 6 0 R>>'
            . "\nstartxref\n" . $xref . "\n%%EOF\n";

        return $out;
    }

    /**
     * Regression test for the root cause of "the quiz was not from my document".
     *
     * The removed fallback scraped parenthesised strings straight out of the raw
     * PDF bytes. On a compressed PDF that yields binary noise plus the document
     * title, which was then handed to the AI as source material - so the AI wrote
     * plausible questions about the title from its own knowledge.
     *
     * looks_like_text() must reject that output, so it can never again be
     * mistaken for document content.
     */
    public function test_scraped_pdf_bytes_are_rejected_as_text(): void {
        $pdf = $this->make_compressed_pdf();

        // Exactly what the old fallback did.
        $scraped = '';
        if (preg_match_all('/\((.*?)\)/s', $pdf, $matches)) {
            $scraped = implode(' ', $matches[1]);
        }

        // The old code's only guard was "is it empty" - and it isn't.
        $this->assertNotEmpty($scraped,
            'Fixture should reproduce the non-empty scrape that defeated the old guard.');

        // None of the real lesson content survives.
        $this->assertStringNotContainsString('Zylonic', $scraped);
        $this->assertStringNotContainsString('Decay Counter', $scraped);
        $this->assertStringNotContainsString('1180', $scraped);

        // But the title does - which is what the AI latched onto.
        $this->assertStringContainsString('Introduction to Computer Networks', $scraped);

        // The fix: this must not be accepted as usable document text.
        $this->assertFalse(pdf_extractor::looks_like_text($scraped),
            'Scraped PDF bytes must never be accepted as extracted text.');
    }

    /**
     * Genuine extracted prose must be accepted.
     */
    public function test_real_prose_is_accepted_as_text(): void {
        $prose = str_repeat(
            'The Zylonic Transport Protocol uses a fourteen byte header and a fixed '
            . 'window size of thirty seven segments. Fragmentation occurs only when '
            . 'the payload exceeds the documented threshold. ',
            4
        );

        $this->assertTrue(pdf_extractor::looks_like_text($prose));
    }

    /**
     * A scanned PDF yields little or nothing; that must be rejected so the caller
     * falls back to having the AI read the file instead of generating from noise.
     */
    public function test_empty_and_trivial_output_is_rejected(): void {
        $this->assertFalse(pdf_extractor::looks_like_text(''));
        $this->assertFalse(pdf_extractor::looks_like_text('   '));
        $this->assertFalse(pdf_extractor::looks_like_text(null));
        $this->assertFalse(pdf_extractor::looks_like_text("\f\f\f"));
        $this->assertFalse(pdf_extractor::looks_like_text('Page 1'));
    }

    /**
     * Raw binary must be rejected outright.
     */
    public function test_binary_is_rejected(): void {
        $binary = random_bytes(4096);
        $this->assertFalse(pdf_extractor::looks_like_text($binary));
    }

    /**
     * A missing file is a hard error, not a silent empty result.
     */
    public function test_missing_file_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        pdf_extractor::extract_pages('/nonexistent/nope.pdf');
    }

    /**
     * Page range parsing.
     */
    public function test_parse_page_range(): void {
        $this->assertEquals(['from' => 10, 'to' => 20], pdf_extractor::parse_page_range('10-20'));
        $this->assertEquals(['from' => 5, 'to' => 15], pdf_extractor::parse_page_range(' 5 - 15 '));
        $this->assertNull(pdf_extractor::parse_page_range(''));
        $this->assertNull(pdf_extractor::parse_page_range(null));
        $this->assertNull(pdf_extractor::parse_page_range('20-10'));
        $this->assertNull(pdf_extractor::parse_page_range('rubbish'));
    }
}
