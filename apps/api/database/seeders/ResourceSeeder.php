<?php

namespace Database\Seeders;

use App\Models\LibraryResource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't duplicate on re-seed.
        if (LibraryResource::query()->exists()) {
            return;
        }

        $samples = [
            [
                'type' => 'template',
                'category' => 'finance',
                'title' => 'Simple cash-flow forecast (spreadsheet)',
                'description' => 'A ready-to-use monthly cash-flow template so you can see money in vs money out three months ahead. Duplicate it and fill in your own numbers.',
                'url' => 'https://www.google.com/sheets/about/',
                'industry' => 'Retail',
            ],
            [
                'type' => 'checklist',
                'category' => 'operations',
                'title' => 'Business registration checklist (South Africa)',
                'description' => 'Everything you need to formally register: CIPC company name, tax number, business bank account and required permits — in the order to do them.',
                'url' => 'https://www.cipc.co.za',
            ],
            [
                'type' => 'toolkit',
                'category' => 'marketing',
                'title' => 'First 100 customers marketing toolkit',
                'description' => 'A short toolkit of low-cost tactics — WhatsApp broadcast, local partnerships, referral asks — to win your first regular customers without an ad budget.',
                'url' => 'https://smallbusiness.co.za',
            ],
            [
                'type' => 'ai_prompt',
                'category' => 'marketing',
                'title' => 'AI prompt: write a week of social posts',
                'description' => 'Copy this prompt into any AI assistant to draft seven on-brand social posts for your business. Fill in the [business] and [offer] placeholders first.',
                'url' => 'https://claude.ai',
            ],
            [
                'type' => 'template',
                'category' => 'sales',
                'title' => 'Quotation & invoice template pack',
                'description' => 'Professional quote and invoice templates you can brand and send in minutes, with the fields SARS expects on a valid tax invoice.',
                'url' => 'https://www.canva.com/invoices/templates/',
            ],
            [
                'type' => 'checklist',
                'category' => 'people',
                'title' => 'Hiring your first employee checklist',
                'description' => 'From writing the role to the UIF and contract paperwork — a step-by-step list so your first hire is compliant and set up to succeed.',
                'url' => 'https://www.labour.gov.za',
            ],
        ];

        foreach ($samples as $sample) {
            LibraryResource::create($sample + ['is_published' => true]);
        }
    }
}
