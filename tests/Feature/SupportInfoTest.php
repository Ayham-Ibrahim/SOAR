<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupportInfoTest extends TestCase
{
    public function test_guest_can_read_support_contacts_and_faq(): void
    {
        $this->getJson('/api/settings/support')
            ->assertOk()
            ->assertJsonPath('data.email', 'koderztech079@gmail.com')
            ->assertJsonPath('data.whatsapp.phone', '+963956955730')
            ->assertJsonPath('data.whatsapp.url', 'https://wa.me/963956955730')
            ->assertJsonStructure(['data' => ['faq' => [['question', 'answer']]]]);
    }
}
