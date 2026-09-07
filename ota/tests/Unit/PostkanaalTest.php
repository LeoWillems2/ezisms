<?php

namespace Tests\Unit;

use App\Support\Postkanaal;
use Tests\TestCase;

/** implementatie/01i §1. */
class PostkanaalTest extends TestCase
{
    public function test_log_en_null_leveren_geen_kanaal(): void
    {
        foreach (['log', 'null'] as $mailer) {
            config(['mail.default' => $mailer]);

            $this->assertFalse(Postkanaal::beschikbaar(), "{$mailer} hoort geen kanaal te zijn");
            $this->assertStringContainsString('MAIL_MAILER', (string) Postkanaal::reden());
        }
    }

    public function test_array_telt_als_kanaal(): void
    {
        // Het transport van de testomgeving en van Mail::fake(); zou het als
        // "geen kanaal" gelden, dan liep de hele suite door de handmatige tak.
        config(['mail.default' => 'array']);

        $this->assertTrue(Postkanaal::beschikbaar());
        $this->assertNull(Postkanaal::reden());
    }

    public function test_smtp_met_een_ingevulde_host_is_een_kanaal(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.example.com']);

        $this->assertTrue(Postkanaal::beschikbaar());
    }

    public function test_smtp_zonder_host_is_geen_kanaal(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '']);

        $this->assertFalse(Postkanaal::beschikbaar());
        $this->assertStringContainsString('MAIL_HOST', (string) Postkanaal::reden());
    }
}
