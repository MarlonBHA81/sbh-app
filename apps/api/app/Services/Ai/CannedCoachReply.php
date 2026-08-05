<?php

namespace App\Services\Ai;

/**
 * Deterministic, honest fallback replies for the AI Coach when no AI provider
 * is configured (the null driver) or a live call fails. These are real, generic
 * small-business guidance — never fabricated data or fake personalisation — and
 * they nudge the member towards a concrete next step.
 */
class CannedCoachReply
{
    /**
     * Build a helpful reply for the member's latest message.
     */
    public static function generate(string $lastUserMessage): string
    {
        $text = mb_strtolower($lastUserMessage);

        $tip = match (true) {
            self::mentions($text, ['proposal', 'quote', 'quotation', 'bid', 'tender', 'apply', 'rfq']) => "A strong proposal is short and specific. Structure it as: (1) a one-line summary of what you'll deliver, (2) your understanding of their need, (3) scope — exactly what's included, (4) timeline with a start and end, (5) price with clear terms, and (6) why you (proof: past work, references, compliance docs like CSD/B-BBEE if relevant). Lead with the outcome they'll get, not your company history.",
            self::mentions($text, ['price', 'pricing', 'charge', 'cost', 'expensive', 'cheap']) => "On pricing: start from your true cost per sale (materials + your time), then add a margin you're comfortable with. Many owners under-charge because they forget to pay themselves. Try raising one price by 10% and watch what happens — you can always adjust.",
            self::mentions($text, ['customer', 'clients', 'sales', 'sell', 'leads', 'market']) => "To win customers, start with people who already trust you. List 20 people who know you, tell each one exactly what you offer and who it helps, then ask for a referral after every job. Word of mouth beats a big ad budget when you're small.",
            self::mentions($text, ['funding', 'loan', 'grant', 'capital', 'money', 'cash flow', 'cashflow']) => 'For funding, first make sure your numbers are clear: a simple month-by-month cash-flow view of money in vs money out. That same view is what lenders and grant panels want to see. Check the Opportunities feed for grants and tenders you can apply to.',
            self::mentions($text, ['staff', 'hire', 'hiring', 'employee', 'team']) => 'Before hiring, write down the exact tasks you want off your plate and how many hours they take. Hire for that specific gap, start part-time if you can, and get the contract and UIF paperwork right from day one.',
            self::mentions($text, ['marketing', 'social', 'instagram', 'facebook', 'whatsapp', 'brand']) => 'Keep marketing simple and consistent: pick one channel your customers actually use, post something useful two or three times a week, and always include a clear next step (a WhatsApp number, a link, a visit). Consistency matters more than perfection.',
            default => 'A good next step is to name the single biggest thing holding your business back right now, then break it into one small action you can take this week. Small, steady steps compound faster than big plans that never start.',
        };

        return $tip."\n\nWhat part would you like to dig into next?";
    }

    /**
     * @param  list<string>  $needles
     */
    private static function mentions(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
