<?php

namespace Tests\Unit;

use App\Support\Specs;
use PHPUnit\Framework\TestCase;

class SpecsTest extends TestCase
{
    public function test_label_value_lines_are_rows(): void
    {
        $text = "Memory: 16GB DDR5\r\nStorage : 512GB SSD M.2 2280 PCIe Gen4\n\nDisplay: 23.8 - 24\" diagonal, FHD (1920 x 1080), IPS";

        $this->assertSame([
            ['Memory', '16GB DDR5'],
            ['Storage', '512GB SSD M.2 2280 PCIe Gen4'],
            ['Display', '23.8 - 24" diagonal, FHD (1920 x 1080), IPS'],
        ], Specs::rows($text));
        $this->assertTrue(Specs::isTable($text));
    }

    public function test_free_text_stays_text(): void
    {
        $this->assertSame([['', '55-inch 4K UHD LED, Smart TV, 3 HDMI']], Specs::rows('55-inch 4K UHD LED, Smart TV, 3 HDMI'));
        $this->assertFalse(Specs::isTable("55-inch 4K UHD\n3 HDMI"));                   // no line with a label
        $this->assertSame([['', 'Aspect ratio 16:9']], Specs::rows('Aspect ratio 16:9'));    // a colon between digits is no label
        $this->assertSame([['Ratio', '16:9']], Specs::rows('Ratio: 16:9'));
        $this->assertFalse(Specs::isTable(null));
    }

    public function test_a_line_without_a_label_continues_the_row_above(): void
    {
        $text = "Supply of one unit\nISO Certification: ISO 9001, ISO 14001 and ISO 27001\nCertification: Manufacturer's or reseller certificate.\nCertification from Microsoft Philippines or an authorized reseller.";

        $this->assertTrue(Specs::isTable($text));
        $this->assertSame([
            ['', 'Supply of one unit'],   // before the first label: a row of its own
            ['ISO Certification', 'ISO 9001, ISO 14001 and ISO 27001'],
            ['Certification', "Manufacturer's or reseller certificate.\nCertification from Microsoft Philippines or an authorized reseller."],
        ], Specs::table($text));
    }
}
