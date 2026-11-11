<?php

namespace Tests\Unit;

use App\Services\WhatsApp\OpenWaClient;
use PHPUnit\Framework\TestCase;

class OpenWaUrlNormalizationTest extends TestCase
{
    /**
     * Test that empty, null, or whitespace-only Base URLs return an empty string.
     */
    public function test_empty_or_whitespace_base_url_returns_empty_string(): void
    {
        $this->assertEquals('', OpenWaClient::normalizeBaseUrl(null));
        $this->assertEquals('', OpenWaClient::normalizeBaseUrl(''));
        $this->assertEquals('', OpenWaClient::normalizeBaseUrl('   '));
        $this->assertEquals('', OpenWaClient::normalizeBaseUrl("\t\n"));
    }

    /**
     * Test that Base URL without trailing slash is normalized to end with /api.
     */
    public function test_normalizes_base_url_without_trailing_slash(): void
    {
        $this->assertEquals(
            'http://100.112.241.87:2785/api',
            OpenWaClient::normalizeBaseUrl('http://100.112.241.87:2785')
        );

        $this->assertEquals(
            'http://localhost:3000/api',
            OpenWaClient::normalizeBaseUrl('http://localhost:3000')
        );

        $this->assertEquals(
            'https://wa-gateway.example.com/api',
            OpenWaClient::normalizeBaseUrl('https://wa-gateway.example.com')
        );
    }

    /**
     * Test that Base URL with trailing slash is cleanly normalized to end with /api without double slashes.
     */
    public function test_normalizes_base_url_with_trailing_slash(): void
    {
        $this->assertEquals(
            'http://100.112.241.87:2785/api',
            OpenWaClient::normalizeBaseUrl('http://100.112.241.87:2785/')
        );

        $this->assertEquals(
            'http://localhost:3000/api',
            OpenWaClient::normalizeBaseUrl('http://localhost:3000///')
        );

        $this->assertEquals(
            'https://wa-gateway.example.com/api',
            OpenWaClient::normalizeBaseUrl('https://wa-gateway.example.com/')
        );
    }

    /**
     * Test that existing /api prefix is preserved without duplicating to /api/api.
     */
    public function test_preserves_existing_api_prefix_without_duplication(): void
    {
        $this->assertEquals(
            'http://100.112.241.87:2785/api',
            OpenWaClient::normalizeBaseUrl('http://100.112.241.87:2785/api')
        );

        $this->assertEquals(
            'http://100.112.241.87:2785/api',
            OpenWaClient::normalizeBaseUrl('http://100.112.241.87:2785/api/')
        );

        $this->assertEquals(
            'https://wa-gateway.example.com/api',
            OpenWaClient::normalizeBaseUrl('https://wa-gateway.example.com/api')
        );

        $this->assertEquals(
            'https://wa-gateway.example.com/api',
            OpenWaClient::normalizeBaseUrl('https://wa-gateway.example.com/api/')
        );
    }

    /**
     * Test that URL missing scheme automatically gets http:// prepended.
     */
    public function test_normalizes_url_missing_scheme(): void
    {
        $this->assertEquals(
            'http://100.112.241.87:2785/api',
            OpenWaClient::normalizeBaseUrl('100.112.241.87:2785')
        );

        $this->assertEquals(
            'http://localhost:3000/api',
            OpenWaClient::normalizeBaseUrl('localhost:3000')
        );

        $this->assertEquals(
            'http://wa.perusahaan.com/api',
            OpenWaClient::normalizeBaseUrl('wa.perusahaan.com')
        );
    }

    /**
     * Test that custom sub-paths before /api are preserved.
     */
    public function test_preserves_custom_subpath_before_api(): void
    {
        $this->assertEquals(
            'https://wa.example.com/my-gateway/api',
            OpenWaClient::normalizeBaseUrl('https://wa.example.com/my-gateway')
        );

        $this->assertEquals(
            'https://wa.example.com/my-gateway/api',
            OpenWaClient::normalizeBaseUrl('https://wa.example.com/my-gateway/api')
        );

        $this->assertEquals(
            'https://wa.example.com/my-gateway/api',
            OpenWaClient::normalizeBaseUrl('https://wa.example.com/my-gateway/api/')
        );
    }

    /**
     * Test that explicit final endpoint URLs are constructed properly.
     */
    public function test_builds_explicit_final_endpoint_urls(): void
    {
        $client = new OpenWaClient(
            baseUrl: 'http://100.112.241.87:2785',
            apiKey: 'mock-key',
            sessionId: 'payroll_session'
        );

        $this->assertEquals('http://100.112.241.87:2785/api', $client->getBaseUrl());
        $this->assertEquals('payroll_session', $client->getSessionId());

        // Explicit final URLs
        $this->assertEquals(
            'http://100.112.241.87:2785/api/sessions/payroll_session/status',
            $client->buildSessionStatusUrl()
        );

        $this->assertEquals(
            'http://100.112.241.87:2785/api/sessions/payroll_session/messages/send-text',
            $client->buildSendTextUrl()
        );

        $this->assertEquals(
            'http://100.112.241.87:2785/api/sessions/payroll_session/messages/send-document',
            $client->buildSendDocumentUrl()
        );

        $this->assertEquals(
            'http://100.112.241.87:2785/api/sessions',
            $client->buildSessionsListUrl()
        );
    }

    /**
     * Test validation of Base URL when empty, malformed, or using unsupported schemes.
     */
    public function test_validate_base_url(): void
    {
        // 1. Empty URL
        $emptyClient = new OpenWaClient(baseUrl: '', apiKey: 'key');
        $this->assertNotNull($emptyClient->validateBaseUrl());
        $this->assertStringContainsString('belum dikonfigurasi', $emptyClient->validateBaseUrl());

        // 2. Unsupported scheme
        $ftpClient = new OpenWaClient(baseUrl: 'ftp://localhost:3000', apiKey: 'key');
        $this->assertNotNull($ftpClient->validateBaseUrl());
        $this->assertStringContainsString('harus menggunakan protokol http:// atau https://', $ftpClient->validateBaseUrl());

        // 3. Valid client
        $validClient = new OpenWaClient(baseUrl: 'http://100.112.241.87:2785', apiKey: 'key');
        $this->assertNull($validClient->validateBaseUrl());
    }

    /**
     * Test Indonesian mobile phone normalization into OpenWA chatId standard ({digits}@c.us).
     */
    public function test_phone_number_normalization_to_openwa_chat_id(): void
    {
        // 1. Converts 08xx to 628xx@c.us
        $this->assertEquals(
            '6289653765212@c.us',
            \App\Support\PhoneNumberNormalizer::toOpenWaChatId('089653765212')
        );

        // 2. Strips spaces, plus signs, hyphens, parentheses, dots
        $this->assertEquals(
            '6289653765212@c.us',
            \App\Support\PhoneNumberNormalizer::toOpenWaChatId('+62 896-5376-5212')
        );
        $this->assertEquals(
            '6281234567890@c.us',
            \App\Support\PhoneNumberNormalizer::toOpenWaChatId('(+62) 0812-3456-7890')
        );

        // 3. Number already ending with @c.us does not get duplicated (@c.us@c.us)
        $this->assertEquals(
            '6289653765212@c.us',
            \App\Support\PhoneNumberNormalizer::toOpenWaChatId('6289653765212@c.us')
        );

        // 4. Already starts with 628
        $this->assertEquals(
            '6281234567890@c.us',
            \App\Support\PhoneNumberNormalizer::toOpenWaChatId('6281234567890')
        );

        // 5. Rejects invalid / non-mobile numbers by returning null
        $this->assertNull(\App\Support\PhoneNumberNormalizer::toOpenWaChatId(''));
        $this->assertNull(\App\Support\PhoneNumberNormalizer::toOpenWaChatId(null));
        $this->assertNull(\App\Support\PhoneNumberNormalizer::toOpenWaChatId('0211234567')); // Landline
        $this->assertNull(\App\Support\PhoneNumberNormalizer::toOpenWaChatId('0800123456')); // Toll-free
        $this->assertNull(\App\Support\PhoneNumberNormalizer::toOpenWaChatId('12345')); // Too short
    }
}
