<?php

namespace Database\Seeders;

use App\Models\AdEvent;
use App\Models\AdSlot;
use App\Models\BusinessCategory;
use App\Models\Campaign;
use App\Models\Challenge;
use App\Models\Cohort;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Disbursement;
use App\Models\Media;
use App\Models\Message;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Programme;
use App\Models\ProgrammeMilestone;
use App\Models\Rank;
use App\Models\Report;
use App\Models\SupplierEnrolment;
use App\Models\Topic;
use App\Models\TopicFollow;
use App\Models\User;
use App\Services\Ads\CampaignService;
use App\Services\Business\BusinessNeedService;
use App\Services\Engagement\CommentService;
use App\Services\Engagement\ReactionService;
use App\Services\MediaService;
use App\Services\MessagingService;
use App\Services\Posts\EventRsvpService;
use App\Services\Posts\PollVoteService;
use App\Services\Posts\PostService;
use App\Services\Posts\QuizAttemptService;
use App\Services\ProfileService;
use App\Services\ReportService;
use App\Support\Geohash;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rich, realistic South-African small-business demo data.
 *
 * Every demo user carries an email in the reserved @demo.sbh space so the
 * dataset can be identified (and wiped) by `demo:seed --fresh` and the master
 * reset. Content is created through the real services wherever practical so
 * counters, satellite rows, mentions, XP, notifications and stats hooks all
 * fire exactly as they would in production.
 *
 * Covered post types: text, link, image, quote, repost, typewriter, magnifier,
 * secret, checkin, blog, poll, quiz, event, job, portfolio and audio.
 * Video is intentionally skipped: producing a genuinely playable video file
 * requires ffmpeg, which is not guaranteed to be available, and a fake file
 * would break the player. Everything else ships with real (generated) assets.
 */
class DemoContentSeeder extends Seeder
{
    private const CITIES = [
        'johannesburg' => ['lat' => -26.2041, 'lng' => 28.0473, 'city' => 'Johannesburg'],
        'cape_town' => ['lat' => -33.9249, 'lng' => 18.4241, 'city' => 'Cape Town'],
        'durban' => ['lat' => -29.8587, 'lng' => 31.0218, 'city' => 'Durban'],
    ];

    /** @var array<string, User> keyed by handle */
    private array $users = [];

    /** @var array<string, Profile> keyed by handle (personal + business) */
    private array $profiles = [];

    /** @var array<string, Post> keyed by a stable content key */
    private array $posts = [];

    private PostService $postService;

    private CommentService $comments;

    private ReactionService $reactions;

    private ProfileService $profileService;

    private MessagingService $messaging;

    private MediaService $media;

    private PollVoteService $pollVotes;

    private QuizAttemptService $quizAttempts;

    private EventRsvpService $rsvps;

    private BusinessNeedService $needs;

    private CampaignService $campaigns;

    private ReportService $reports;

    public function run(): void
    {
        $this->postService = app(PostService::class);
        $this->comments = app(CommentService::class);
        $this->reactions = app(ReactionService::class);
        $this->profileService = app(ProfileService::class);
        $this->messaging = app(MessagingService::class);
        $this->media = app(MediaService::class);
        $this->pollVotes = app(PollVoteService::class);
        $this->quizAttempts = app(QuizAttemptService::class);
        $this->rsvps = app(EventRsvpService::class);
        $this->needs = app(BusinessNeedService::class);
        $this->campaigns = app(CampaignService::class);
        $this->reports = app(ReportService::class);

        // Reference data first (all idempotent updateOrCreate seeders).
        $this->call([
            BadgeSeeder::class,
            RankSeeder::class,
            XpActionSeeder::class,
            TopicSeeder::class,
            BusinessCategorySeeder::class,
            SettingSeeder::class,
        ]);

        $this->createUsers();
        $this->createBusinessProfiles();
        $this->seedEsd();
        $this->buildFollowGraph();
        $this->followTopics();
        $this->createPosts();
        $this->engage();
        $this->seedMessaging();
        $this->seedChallenge();
        $this->seedBusinessNeeds();
        $this->backfillXpHistory();
        $this->seedAds();
        $this->backfillPostStats();
        $this->seedReports();
    }

    // ------------------------------------------------------------------
    // Users & profiles
    // ------------------------------------------------------------------

    private function createUsers(): void
    {
        $people = [
            // handle, name, city key, share_location, is_private
            ['thabo', 'Thabo Mokoena', 'johannesburg', true, false],
            ['lerato', 'Lerato Dlamini', 'johannesburg', true, false],
            ['sipho', 'Sipho Ndlovu', 'durban', true, false],
            ['zanele', 'Zanele Khumalo', 'cape_town', true, false],
            ['pieter', 'Pieter van der Merwe', 'cape_town', false, false],
            ['aisha', 'Aisha Patel', 'durban', true, false],
            ['mandla', 'Mandla Zulu', 'johannesburg', false, false],
            ['nadia', 'Nadia Booysen', 'cape_town', true, false],
            ['bongani', 'Bongani Mthembu', 'durban', false, false],
            ['karabo', 'Karabo Molefe', 'johannesburg', true, false],
            ['precious', 'Precious Nkosi', 'johannesburg', false, false],
            ['dineo', 'Dineo Sithole', 'johannesburg', true, false],
            ['anele', 'Anele Gumede', 'durban', false, true],
            ['francois', 'Francois du Plessis', 'cape_town', false, true],
        ];

        $bios = [
            'thabo' => 'Braai master and small-business dreamer. Joburg born and bred. 🔥',
            'lerato' => 'Software developer helping SMEs go digital. Coffee first. ☕',
            'sipho' => 'Durban surfer, spice lover, weekend market regular. 🏄',
            'zanele' => 'Beauty entrepreneur in the Mother City. Building Protea Beauty. 💅',
            'pieter' => 'Logistics is my love language. Trucks, routes and spreadsheets.',
            'aisha' => 'Third-generation spice trader on the Bluff. 🌶️',
            'mandla' => 'Photographer. I shoot storefronts, food and people who hustle.',
            'nadia' => 'Marketing consultant. I make small brands look big.',
            'bongani' => 'Fixes anything with a motor. Ask me about bakkies.',
            'karabo' => 'Creative director at Joburg Creatives. Design is a business tool.',
            'precious' => 'Bookkeeper for hire. Numbers do not lie, people do. 😄',
            'dineo' => 'Learning to bake commercially. Sourdough era. 🍞',
            'anele' => 'Keeping it low key.',
            'francois' => 'Private account. Wine farm life.',
        ];

        foreach ($people as [$handle, $name, $cityKey, $shareLocation, $isPrivate]) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $handle.'@demo.sbh',
                'password' => Hash::make('password'),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $city = self::CITIES[$cityKey];

            $profile = $user->profiles()->create([
                'kind' => Profile::KIND_PERSONAL,
                'handle' => $handle,
                'name' => $name,
                'bio' => $bios[$handle],
                'lat' => $shareLocation ? $city['lat'] : null,
                'lng' => $shareLocation ? $city['lng'] : null,
                'geohash' => $shareLocation ? Geohash::encode($city['lat'], $city['lng']) : null,
                'city' => $city['city'],
                'country_code' => 'ZA',
                'share_location' => $shareLocation,
                'is_private' => $isPrivate,
                'location' => $city['city'].', South Africa',
            ]);

            $this->attachProfileImages($profile);

            $this->users[$handle] = $user;
            $this->profiles[$handle] = $profile;
        }
    }

    private function createBusinessProfiles(): void
    {
        $businesses = [
            // owner, handle, name, category slug, city key, bio
            ['thabo', 'braai_bros', 'Braai Bros Catering', 'food-beverage', 'johannesburg',
                'Flame-grilled catering for events across Gauteng. Shisa nyama done right. 🔥🥩'],
            ['lerato', 'ubuntu_tech', 'Ubuntu Tech Solutions', 'tech-it', 'johannesburg',
                'Websites, POS systems and WhatsApp bots for township businesses.'],
            ['zanele', 'protea_beauty', 'Protea Beauty Studio', 'beauty-wellness', 'cape_town',
                'Nails, lashes and skincare in Woodstock. Walk-ins welcome. 💇'],
            ['aisha', 'durban_spice', 'Durban Spice Traders', 'retail', 'durban',
                'Wholesale and retail spices since 1974. If we do not stock it, it does not exist.'],
            ['pieter', 'karoo_logistics', 'Karoo Logistics', 'transport-logistics', 'cape_town',
                'Cold-chain and general freight between the Cape and Gauteng.'],
            ['karabo', 'joburg_creatives', 'Joburg Creatives', 'marketing-media', 'johannesburg',
                'Branding, social media and design for South African SMEs.'],
        ];

        foreach ($businesses as [$owner, $handle, $name, $categorySlug, $cityKey, $bio]) {
            $city = self::CITIES[$cityKey];
            $category = BusinessCategory::query()->where('slug', $categorySlug)->first();

            $profile = $this->users[$owner]->profiles()->create([
                'kind' => Profile::KIND_BUSINESS,
                'handle' => $handle,
                'name' => $name,
                'bio' => $bio,
                'category' => $categorySlug,
                'business_category_id' => $category?->id,
                'website' => 'https://'.str_replace('_', '', $handle).'.example.co.za',
                'lat' => $city['lat'],
                'lng' => $city['lng'],
                'geohash' => Geohash::encode($city['lat'], $city['lng']),
                'city' => $city['city'],
                'country_code' => 'ZA',
                'share_location' => true,
                'location' => $city['city'].', South Africa',
            ]);

            $this->attachProfileImages($profile, withCover: true);

            // Outbound social links so the profile icon row has demo data.
            $profile->forceFill([
                'social_links' => [
                    'linkedin' => 'https://linkedin.com/company/'.$handle,
                    'facebook' => 'https://facebook.com/'.$handle,
                    'instagram' => 'https://instagram.com/'.$handle,
                    'whatsapp' => 'https://wa.me/2782'.str_pad((string) (crc32($handle) % 10000000), 7, '0', STR_PAD_LEFT),
                ],
            ])->save();

            $this->profiles[$handle] = $profile;
        }
    }

    /**
     * A corporate ESD sponsor with a live supplier-development programme:
     * verified suppliers enrolled across the state machine, with milestones and
     * planned-vs-actual disbursements so the ESD portal and reports have data.
     */
    private function seedEsd(): void
    {
        // Corporate sponsor account (owns the programme; verified by default).
        $corpUser = User::query()->create([
            'name' => 'Aurora Holdings',
            'email' => 'aurora@demo.sbh',
            'password' => Hash::make('password'),
        ]);
        $corpUser->forceFill(['email_verified_at' => now()])->save();

        $jhb = self::CITIES['johannesburg'];
        $corporate = $corpUser->profiles()->create([
            'kind' => Profile::KIND_CORPORATE,
            'handle' => 'aurora_holdings',
            'name' => 'Aurora Holdings',
            'bio' => 'Enterprise & supplier development for South African SMMEs. Growing the businesses in our value chain. 🌅',
            'city' => $jhb['city'],
            'country_code' => 'ZA',
            'location' => $jhb['city'].', South Africa',
        ]);
        $corporate->forceFill(['is_verified' => true])->save();
        $this->attachProfileImages($corporate, withCover: true);
        $this->profiles['aurora_holdings'] = $corporate;

        // Verify a handful of existing demo businesses so they can be enrolled
        // as suppliers (and so the verification demo has approved businesses).
        foreach (['durban_spice', 'karoo_logistics', 'ubuntu_tech', 'protea_beauty'] as $handle) {
            $this->profiles[$handle]->forceFill(['is_verified' => true])->save();
        }

        $programme = Programme::create([
            'profile_id' => $corporate->id,
            'name' => 'Aurora Supplier Development Programme',
            'description' => 'A 12-month accelerator supporting verified SMME suppliers in the Aurora value chain with grants, mentorship and market access.',
            'type' => Programme::TYPE_SUPPLIER_DEVELOPMENT,
            'status' => Programme::STATUS_ACTIVE,
            'starts_at' => now()->subMonths(2)->startOfMonth(),
            'ends_at' => now()->addMonths(10)->endOfMonth(),
            'budget_cents' => 2_500_000_00,
            'created_by' => $corpUser->id,
        ]);

        $cohort = Cohort::create([
            'programme_id' => $programme->id,
            'name' => '2026 Intake',
            'starts_at' => now()->subMonths(2)->startOfMonth(),
            'ends_at' => now()->addMonths(10)->endOfMonth(),
            'capacity' => 8,
            'status' => Cohort::STATUS_ACTIVE,
        ]);

        // supplier handle => [status, [milestone => complete?], [disbursement …]]
        $enrolments = [
            'durban_spice' => [
                'status' => SupplierEnrolment::STATUS_ACTIVE,
                'milestones' => [
                    ['Complete financial-management workshop', true],
                    ['Obtain tax clearance certificate', true],
                    ['Secure first corporate purchase order', false],
                ],
                'disbursements' => [
                    ['grant', 50_000_00, true, 'Working-capital grant'],
                    ['grant', 30_000_00, false, 'Equipment co-funding (planned)'],
                ],
            ],
            'karoo_logistics' => [
                'status' => SupplierEnrolment::STATUS_COMPLETED,
                'milestones' => [
                    ['Fleet cold-chain certification', true],
                    ['Onboard to corporate supplier portal', true],
                ],
                'disbursements' => [
                    ['loan', 75_000_00, true, 'Vehicle-finance bridge'],
                ],
            ],
            'ubuntu_tech' => [
                'status' => SupplierEnrolment::STATUS_ACCEPTED,
                'milestones' => [
                    ['Draft POS integration proposal', false],
                ],
                'disbursements' => [
                    ['in_kind', 15_000_00, false, 'Mentorship hours (planned)'],
                ],
            ],
            'protea_beauty' => [
                'status' => SupplierEnrolment::STATUS_INVITED,
                'milestones' => [],
                'disbursements' => [],
            ],
        ];

        foreach ($enrolments as $handle => $spec) {
            $confirmed = in_array($spec['status'], [
                SupplierEnrolment::STATUS_ACCEPTED,
                SupplierEnrolment::STATUS_ACTIVE,
                SupplierEnrolment::STATUS_COMPLETED,
            ], true);

            $enrolment = SupplierEnrolment::create([
                'cohort_id' => $cohort->id,
                'profile_id' => $this->profiles[$handle]->id,
                'status' => $spec['status'],
                'enrolled_at' => $confirmed ? now()->subMonths(2) : null,
                'created_by' => $corpUser->id,
            ]);

            foreach ($spec['milestones'] as [$title, $complete]) {
                ProgrammeMilestone::create([
                    'supplier_enrolment_id' => $enrolment->id,
                    'title' => $title,
                    'due_at' => now()->addMonth(),
                    'status' => $complete ? ProgrammeMilestone::STATUS_COMPLETE : ProgrammeMilestone::STATUS_PENDING,
                    'completed_at' => $complete ? now()->subWeeks(2) : null,
                ]);
            }

            foreach ($spec['disbursements'] as [$kind, $cents, $paid, $reference]) {
                Disbursement::create([
                    'supplier_enrolment_id' => $enrolment->id,
                    'amount_cents' => $cents,
                    'currency' => 'ZAR',
                    'kind' => $kind,
                    'disbursed_at' => $paid ? now()->subMonth() : null,
                    'reference' => $reference,
                    'created_by' => $corpUser->id,
                ]);
            }
        }
    }

    private function buildFollowGraph(): void
    {
        $edges = [
            // follower => list of followed handles
            'thabo' => ['lerato', 'sipho', 'mandla', 'karabo', 'precious', 'ubuntu_tech', 'joburg_creatives'],
            'lerato' => ['thabo', 'zanele', 'karabo', 'nadia', 'braai_bros', 'durban_spice'],
            'sipho' => ['thabo', 'aisha', 'bongani', 'durban_spice', 'braai_bros'],
            'zanele' => ['lerato', 'nadia', 'aisha', 'protea_beauty', 'joburg_creatives'],
            'pieter' => ['thabo', 'lerato', 'karoo_logistics', 'durban_spice'],
            'aisha' => ['sipho', 'zanele', 'durban_spice', 'braai_bros'],
            'mandla' => ['thabo', 'karabo', 'dineo', 'joburg_creatives'],
            'nadia' => ['zanele', 'karabo', 'lerato', 'protea_beauty', 'joburg_creatives'],
            'bongani' => ['sipho', 'thabo', 'karoo_logistics'],
            'karabo' => ['thabo', 'lerato', 'mandla', 'nadia', 'ubuntu_tech'],
            'precious' => ['thabo', 'lerato', 'dineo', 'braai_bros'],
            'dineo' => ['thabo', 'precious', 'mandla', 'braai_bros', 'protea_beauty'],
            'anele' => ['sipho', 'aisha'],
            'francois' => ['pieter', 'zanele'],
        ];

        foreach ($edges as $follower => $targets) {
            foreach ($targets as $target) {
                $this->profileService->follow($this->profiles[$follower], $this->profiles[$target]);
            }
        }

        // A pending follow request to a private profile (anele is private).
        $this->profileService->follow($this->profiles['thabo'], $this->profiles['anele']);
        $this->profileService->follow($this->profiles['dineo'], $this->profiles['francois']);
    }

    private function followTopics(): void
    {
        $follows = [
            'thabo' => ['small-business', 'entrepreneurship', 'recipes'],
            'lerato' => ['web-dev', 'ai', 'startups', 'small-business'],
            'zanele' => ['branding', 'social-media', 'wellness'],
            'aisha' => ['retail', 'recipes', 'small-business'],
            'karabo' => ['graphic-design', 'branding', 'advertising'],
            'nadia' => ['social-media', 'seo', 'branding'],
            'mandla' => ['photography', 'graphic-design'],
            'sipho' => ['local-news', 'fitness'],
        ];

        foreach ($follows as $handle => $slugs) {
            foreach ($slugs as $slug) {
                $topic = Topic::query()->where('slug', $slug)->first();

                if ($topic === null) {
                    continue;
                }

                TopicFollow::query()->firstOrCreate([
                    'profile_id' => $this->profiles[$handle]->id,
                    'topic_id' => $topic->id,
                ]);

                $topic->increment('followers_count');
            }
        }
    }

    // ------------------------------------------------------------------
    // Posts
    // ------------------------------------------------------------------

    private function createPosts(): void
    {
        $this->createTextPosts();
        $this->createLinkPost();
        $this->createImagePosts();
        $this->createQuoteAndRepost();
        $this->createNoveltyPosts();   // typewriter, magnifier, secret, checkin
        $this->createBlogPost();
        $this->createPolls();
        $this->createQuiz();
        $this->createEvents();
        $this->createJobs();
        $this->createPortfolio();
        $this->createAudioPost();
        // NOTE: no video post is seeded. A playable video cannot be produced
        // without ffmpeg, and a dummy file would render a broken player.
        $this->createDraftAndScheduled();
        $this->backdatePosts();
    }

    /**
     * Create a published post through PostService so all hooks fire.
     */
    private function post(string $key, string $author, array $data): Post
    {
        $post = $this->postService->create(
            $this->users[$this->ownerHandle($author)],
            $this->profiles[$author],
            $data,
        );

        return $this->posts[$key] = $post;
    }

    /**
     * Personal handle of the user that owns the given profile handle.
     */
    private function ownerHandle(string $profileHandle): string
    {
        $userId = $this->profiles[$profileHandle]->user_id;

        foreach ($this->users as $handle => $user) {
            if ($user->id === $userId) {
                return $handle;
            }
        }

        return $profileHandle;
    }

    private function createTextPosts(): void
    {
        $this->post('text_welcome', 'thabo', [
            'type' => 'text',
            'body' => 'First post! 🎉 Building @braai_bros one weekend gig at a time. Big thanks to @lerato for the new booking site and @karabo for the logo. Joburg, we are open for business! 🔥',
            'topic_ids' => ['small-business', 'entrepreneurship'],
        ]);

        $this->post('text_tip', 'lerato', [
            'type' => 'text',
            'body' => 'SME tip of the day: a WhatsApp Business catalogue is free and takes 20 minutes to set up. Half my clients doubled their orders with just that. 📱💡 Ask me anything below.',
            'topic_ids' => ['small-business', 'web-dev'],
        ]);

        $this->post('text_market', 'aisha', [
            'type' => 'text',
            'body' => 'Saturday market haul is packed: garam masala, smoked paprika and our famous mother-in-law masala. 🌶️ Come find @durban_spice at the Bluff market from 7am! cc @sipho',
            'topic_ids' => ['retail', 'recipes'],
        ]);

        $this->post('text_design', 'karabo', [
            'type' => 'text',
            'body' => 'Hot take: your logo matters less than your Google reviews. Fix the service first, then call @joburg_creatives for the rebrand. 😄',
            'topic_ids' => ['branding'],
        ]);

        $this->post('text_biz_update', 'braai_bros', [
            'type' => 'text',
            'body' => 'Weekend fully booked! 🥩🔥 Two weddings and a corporate year-end. Thank you Joburg — December is filling up fast, DM us to secure a date.',
            'topic_ids' => ['small-business'],
        ]);

        $this->post('text_spice_promo', 'durban_spice', [
            'type' => 'text',
            'body' => 'Trade accounts now open for restaurants and caterers — wholesale pricing on our full range. 🌶️ DM @durban_spice for the price list.',
            'topic_ids' => ['retail'],
        ]);

        // A followers-only post.
        $this->post('text_followers_only', 'zanele', [
            'type' => 'text',
            'body' => 'Followers only: soft launch of the new lash menu this Friday. First five bookings get 50% off. 🤫',
            'visibility' => Post::VISIBILITY_FOLLOWERS,
        ]);

        // A sensitive-flagged post.
        $this->post('text_sensitive', 'bongani', [
            'type' => 'text',
            'body' => 'Workshop reality: burst hydraulic line sprayed the whole bay. Photos are not for the squeamish — cleanup took all night.',
            'sensitive' => true,
        ]);
    }

    private function createLinkPost(): void
    {
        $this->post('link_sars', 'precious', [
            'type' => 'link',
            'body' => 'Bookkeepers, bookmark this. Provisional tax deadlines catch small businesses every single year.',
            'payload' => [
                'url' => 'https://www.sars.gov.za/types-of-tax/provisional-tax/',
                'title' => 'Provisional Tax — SARS',
                'description' => 'What provisional tax is, who must pay it, and when it is due.',
            ],
            'topic_ids' => ['taxes', 'small-business'],
        ]);
    }

    private function createImagePosts(): void
    {
        $images = [
            ['image_braai', 'braai_bros', 'BB', '#C2410C', 'Sunday special: lamb tjops and pap straight off the fire. 🔥 Tag someone who needs this.', ['recipes']],
            ['image_nails', 'protea_beauty', 'PB', '#BE185D', 'Fresh set Friday at the studio. 💅 Bookings open for next week.', ['wellness']],
            ['image_shoot', 'mandla', 'MZ', '#1D4ED8', 'Storefront shoot for a client in Maboneng today. Golden hour did the heavy lifting. 📷', ['photography']],
        ];

        foreach ($images as [$key, $author, $initials, $color, $body, $topics]) {
            $media = $this->makeImage($this->profiles[$author], $initials, $color);

            $this->post($key, $author, [
                'type' => 'image',
                'body' => $body,
                'media_ids' => [$media->ulid],
                'topic_ids' => $topics,
            ]);
        }
    }

    private function createQuoteAndRepost(): void
    {
        $this->post('quote_tip', 'thabo', [
            'type' => 'quote',
            'body' => 'Can confirm — did this for Braai Bros and the Saturday bookings went mad. 📈 Thanks @lerato!',
            'parent_post_id' => $this->posts['text_tip']->ulid,
        ]);

        $this->post('repost_market', 'sipho', [
            'type' => 'repost',
            'parent_post_id' => $this->posts['text_market']->ulid,
        ]);
    }

    private function createNoveltyPosts(): void
    {
        $this->post('typewriter', 'dineo', [
            'type' => 'typewriter',
            'payload' => [
                'text' => 'Day 47 of the sourdough experiment... the starter finally survived a Joburg winter. Commercial licence application goes in on Monday. 🍞',
                'speed' => 40,
            ],
        ]);

        $this->post('magnifier', 'nadia', [
            'type' => 'magnifier',
            'payload' => [
                'text' => 'Look closely at your competitors before you price yourself. The details tell you everything.',
            ],
        ]);

        $this->post('secret', 'karabo', [
            'type' => 'secret',
            'payload' => [
                'secret_text' => 'We are pitching for the biggest retail account in Gauteng next month. Say nothing. 🤐',
            ],
        ]);

        $jhb = self::CITIES['johannesburg'];
        $this->post('checkin', 'thabo', [
            'type' => 'checkin',
            'body' => 'Scouting the venue for Saturday. This view though! 🏙️',
            'payload' => ['place_name' => 'Neighbourgoods Market, Braamfontein'],
            'lat' => $jhb['lat'],
            'lng' => $jhb['lng'],
            'city' => 'Johannesburg',
            'country_code' => 'ZA',
        ]);
    }

    private function createBlogPost(): void
    {
        $this->post('blog', 'lerato', [
            'type' => 'blog',
            'payload' => [
                'title' => 'Going digital on a shoestring: a guide for SA small businesses',
                'excerpt' => 'You do not need a big budget to look professional online. Here is the exact stack I set up for clients under R500 a month.',
                'doc' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                            ['type' => 'text', 'text' => 'Start with the basics'],
                        ]],
                        ['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => 'Most township businesses I work with need exactly three things online: a way to be found, a way to be contacted, and a way to take payment.'],
                        ]],
                        ['type' => 'bulletList', 'content' => [
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                                ['type' => 'text', 'text' => 'Google Business Profile — free, and it puts you on the map. Literally.'],
                            ]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                                ['type' => 'text', 'text' => 'WhatsApp Business — catalogue, quick replies, away messages.'],
                            ]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                                ['type' => 'text', 'text' => 'SnapScan or Yoco — card payments without a bank meeting.'],
                            ]]]],
                        ]],
                        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                            ['type' => 'text', 'text' => 'What to skip (for now)'],
                        ]],
                        ['type' => 'orderedList', 'content' => [
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                                ['type' => 'text', 'text' => 'A custom app. Nobody will download it.'],
                            ]]]],
                            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                                ['type' => 'text', 'text' => 'Paid ads before you have reviews.'],
                            ]]]],
                        ]],
                        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => 'Digital is not a project you finish. It is a habit you keep.'],
                        ]]]],
                    ],
                ],
            ],
            'topic_ids' => ['web-dev', 'small-business'],
        ]);
    }

    private function createPolls(): void
    {
        // Open poll with votes from several profiles.
        $open = $this->post('poll_open', 'braai_bros', [
            'type' => 'poll',
            'body' => 'Settle it once and for all: what is the essential braai side?',
            'payload' => [
                'options' => ['Pap & chakalaka', 'Braaibroodjies', 'Potato salad', 'Creamed spinach'],
                'duration_hours' => 168,
            ],
            'topic_ids' => ['recipes'],
        ]);

        $openOptions = $open->poll->options->sortBy('position')->values();
        foreach ([
            'lerato' => 0, 'sipho' => 1, 'aisha' => 0, 'mandla' => 1,
            'precious' => 0, 'dineo' => 2, 'karabo' => 1, 'nadia' => 3,
        ] as $voter => $optionIndex) {
            $this->pollVotes->vote($this->profiles[$voter], $open, $openOptions[$optionIndex]->id);
        }

        // Ended poll: votes are cast while open, then ends_at is backdated.
        $ended = $this->post('poll_ended', 'nadia', [
            'type' => 'poll',
            'body' => 'Which social platform brings your business the most actual sales?',
            'payload' => [
                'options' => ['WhatsApp', 'Instagram', 'Facebook', 'TikTok'],
                'duration_hours' => 24,
            ],
            'topic_ids' => ['social-media'],
        ]);

        $endedOptions = $ended->poll->options->sortBy('position')->values();
        foreach (['thabo' => 0, 'zanele' => 1, 'aisha' => 0, 'karabo' => 3, 'lerato' => 0] as $voter => $optionIndex) {
            $this->pollVotes->vote($this->profiles[$voter], $ended, $endedOptions[$optionIndex]->id);
        }

        $ended->poll->update(['ends_at' => now()->subDays(2)]);
    }

    private function createQuiz(): void
    {
        $quiz = $this->post('quiz', 'precious', [
            'type' => 'quiz',
            'body' => 'Think you know SA small-business tax? Prove it. 🧮',
            'payload' => [
                'questions' => [
                    [
                        'question' => 'Below which annual turnover can a business register as a micro business for turnover tax?',
                        'options' => ['R500 000', 'R1 million', 'R5 million', 'R10 million'],
                        'correct_index' => 1,
                    ],
                    [
                        'question' => 'What is the standard VAT rate in South Africa (2025)?',
                        'options' => ['14%', '15%', '16%', '18%'],
                        'correct_index' => 1,
                    ],
                    [
                        'question' => 'When must a business register for VAT?',
                        'options' => [
                            'Turnover over R1m in 12 months',
                            'After the first employee',
                            'Immediately at registration',
                            'Only if importing goods',
                        ],
                        'correct_index' => 0,
                    ],
                ],
            ],
            'topic_ids' => ['taxes'],
        ]);

        foreach ([
            'thabo' => [1, 1, 0],   // 100%
            'dineo' => [0, 1, 2],   // 33%
            'lerato' => [1, 1, 3],  // 67%
            'karabo' => [3, 0, 1],  // 0%
        ] as $handle => $answers) {
            $this->quizAttempts->attempt($this->profiles[$handle], $quiz, $answers);
        }
    }

    private function createEvents(): void
    {
        // Upcoming event with RSVPs.
        $upcoming = $this->post('event_upcoming', 'joburg_creatives', [
            'type' => 'event',
            'body' => 'Free workshop for small-business owners — bring your phone, leave with a content plan. ☕🥐 Coffee and koeksisters on us.',
            'payload' => [
                'title' => 'DIY Social Media for Small Business',
                'starts_at' => now()->addDays(10)->setTime(9, 0)->toDateTimeString(),
                'ends_at' => now()->addDays(10)->setTime(13, 0)->toDateTimeString(),
                'venue' => 'Victoria Yards, Lorentzville, Johannesburg',
            ],
            'topic_ids' => ['social-media', 'small-business'],
        ]);

        foreach ([
            'thabo' => 'going', 'lerato' => 'going', 'dineo' => 'going',
            'precious' => 'interested', 'mandla' => 'interested', 'nadia' => 'going',
        ] as $handle => $status) {
            $this->rsvps->rsvp($this->profiles[$handle], $upcoming, $status);
        }

        // Past event: created in the future (publish guard requires it), then
        // backdated so the demo timeline has history.
        $past = $this->post('event_past', 'durban_spice', [
            'type' => 'event',
            'body' => 'Our winter tasting evening — thank you Durban for showing up in numbers!',
            'payload' => [
                'title' => 'Winter Curry Tasting Evening',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addDay()->addHours(3)->toDateTimeString(),
                'venue' => 'Durban Spice Traders, The Bluff',
            ],
        ]);

        foreach (['sipho' => 'going', 'aisha' => 'going', 'bongani' => 'interested'] as $handle => $status) {
            $this->rsvps->rsvp($this->profiles[$handle], $past, $status);
        }

        $past->event->update([
            'starts_at' => now()->subDays(12)->setTime(18, 0),
            'ends_at' => now()->subDays(12)->setTime(21, 0),
        ]);
    }

    private function createJobs(): void
    {
        $this->post('job_open', 'ubuntu_tech', [
            'type' => 'job',
            'body' => 'We are growing! Come build software that actually matters to real businesses.',
            'payload' => [
                'title' => 'Junior Laravel Developer',
                'company' => 'Ubuntu Tech Solutions',
                'location' => 'Johannesburg (hybrid)',
                'employment_type' => 'full_time',
                'salary_min' => 25000,
                'salary_max' => 35000,
                'currency' => 'ZAR',
                'apply_url' => 'https://ubuntutech.example.co.za/careers/junior-dev',
                'expires_at' => now()->addDays(21)->toDateTimeString(),
            ],
            'topic_ids' => ['web-dev'],
        ]);

        $expired = $this->post('job_expired', 'karoo_logistics', [
            'type' => 'job',
            'body' => 'Position filled — thanks to everyone who applied.',
            'payload' => [
                'title' => 'Code 14 Driver (Cape Town–Joburg route)',
                'company' => 'Karoo Logistics',
                'location' => 'Cape Town',
                'employment_type' => 'contract',
                'salary_min' => 18000,
                'salary_max' => 22000,
                'currency' => 'ZAR',
                'expires_at' => now()->subDays(5)->toDateTimeString(),
            ],
        ]);

        // Keep the reference so backdating skips satellite-sensitive posts.
        unset($expired);
    }

    private function createPortfolio(): void
    {
        $items = [
            ['JC', '#7C3AED'],
            ['LO', '#0F766E'],
            ['GO', '#B91C1C'],
            ['BR', '#A16207'],
        ];

        $mediaIds = [];
        foreach ($items as [$initials, $color]) {
            $mediaIds[] = $this->makeImage($this->profiles['joburg_creatives'], $initials, $color)->ulid;
        }

        $this->post('portfolio', 'joburg_creatives', [
            'type' => 'portfolio',
            'payload' => [
                'title' => 'Brand identities, 2026 so far',
                'description' => 'A selection of logo and identity work for South African small businesses this year — spice traders, coffee roasters and one very stubborn plumber.',
            ],
            'media_ids' => $mediaIds,
            'topic_ids' => ['graphic-design', 'branding'],
        ]);
    }

    private function createAudioPost(): void
    {
        $audio = $this->makeAudio($this->profiles['nadia']);

        $this->post('audio', 'nadia', [
            'type' => 'audio',
            'body' => 'Mini podcast: 2 minutes on why your business name matters less than you think. 🎙️',
            'payload' => ['title' => 'Small Biz Sound Bite #1'],
            'media_ids' => [$audio->ulid],
            'topic_ids' => ['branding'],
        ]);
    }

    private function createDraftAndScheduled(): void
    {
        $this->post('draft', 'thabo', [
            'type' => 'text',
            'body' => 'DRAFT: pricing announcement for the December packages. Do not publish before confirming supplier costs.',
            'status' => Post::STATUS_DRAFT,
        ]);

        $this->post('scheduled', 'thabo', [
            'type' => 'text',
            'body' => 'It is braai o’clock somewhere. 🔥 Book your December event with @braai_bros before the calendar fills up!',
            'status' => Post::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
        ]);
    }

    /**
     * Spread the simple published posts over the past ~3 weeks so feeds,
     * charts and leaderboards have history. Satellite-bearing posts whose
     * behaviour depends on relative time (open poll, upcoming event, open job)
     * keep their real timestamps.
     */
    private function backdatePosts(): void
    {
        $backdatable = [
            'text_welcome' => 20, 'text_tip' => 16, 'text_market' => 13,
            'text_design' => 11, 'link_sars' => 15, 'image_braai' => 9,
            'image_nails' => 8, 'image_shoot' => 7, 'quote_tip' => 12,
            'repost_market' => 10, 'typewriter' => 6, 'magnifier' => 5,
            'secret' => 4, 'checkin' => 3, 'blog' => 14, 'poll_ended' => 6,
            'text_spice_promo' => 12,
            'event_past' => 15, 'job_expired' => 18, 'portfolio' => 2,
            'audio' => 1, 'text_biz_update' => 2, 'quiz' => 8,
        ];

        foreach ($backdatable as $key => $daysAgo) {
            if (! isset($this->posts[$key])) {
                continue;
            }

            $when = now()->subDays($daysAgo)->setTime(9 + ($daysAgo % 9), 15);

            $this->posts[$key]->forceFill([
                'created_at' => $when,
                'published_at' => $when,
            ])->save();
        }
    }

    // ------------------------------------------------------------------
    // Engagement: comments, likes, votes
    // ------------------------------------------------------------------

    private function engage(): void
    {
        // Threaded comments (depth 0 → 1 → 2) with mentions on the tip post.
        $tip = $this->posts['text_tip'];
        $c1 = $this->comment('thabo', $tip, 'This is gold. Set mine up during load shedding, took 25 minutes on data. 😅');
        $c2 = $this->comment('lerato', $tip, '@thabo ha! Respect. Send me a screenshot and I will sanity-check the catalogue layout.', $c1->ulid);
        $c3 = $this->comment('thabo', $tip, '@lerato deal — sending it tonight. 🙏', $c2->ulid);
        $c4 = $this->comment('precious', $tip, 'Adding this to the onboarding checklist I give my bookkeeping clients.');

        // Comments on the braai image post.
        $braai = $this->posts['image_braai'];
        $b1 = $this->comment('sipho', $braai, 'Driving up from Durban for this, no jokes. 🤤');
        $this->comment('braai_bros', $braai, '@sipho the fire will be waiting boet. 🔥', $b1->ulid);
        $this->comment('dineo', $braai, 'Bringing the sourdough if you save me a tjop.');

        // Comments on the blog.
        $blog = $this->posts['blog'];
        $g1 = $this->comment('zanele', $blog, 'The Yoco tip alone saved me a fortune in bank fees. Great write-up @lerato!');
        $this->comment('lerato', $blog, '@zanele that makes my whole week. 🎉', $g1->ulid);

        // A comment thread on the open poll.
        $this->comment('mandla', $this->posts['poll_open'], 'Braaibroodjies and it is not even close.');

        // Likes spread across posts.
        $likes = [
            'text_welcome' => ['lerato', 'sipho', 'karabo', 'precious', 'mandla', 'dineo'],
            'text_tip' => ['thabo', 'zanele', 'aisha', 'precious', 'nadia', 'karabo', 'dineo'],
            'text_market' => ['sipho', 'zanele', 'bongani'],
            'image_braai' => ['sipho', 'dineo', 'precious', 'thabo', 'lerato'],
            'image_nails' => ['nadia', 'aisha', 'dineo'],
            'image_shoot' => ['karabo', 'thabo'],
            'blog' => ['zanele', 'thabo', 'precious', 'nadia', 'karabo'],
            'quote_tip' => ['lerato', 'karabo'],
            'portfolio' => ['nadia', 'mandla', 'thabo', 'lerato'],
            'audio' => ['zanele', 'karabo'],
            'event_upcoming' => ['thabo', 'dineo', 'precious'],
            'checkin' => ['mandla'],
        ];

        foreach ($likes as $key => $handles) {
            foreach ($handles as $handle) {
                $this->reactions->like($this->profiles[$handle], $this->posts[$key]);
            }
        }

        // Votes (up/down) on a few posts.
        foreach (['lerato' => 1, 'sipho' => 1, 'mandla' => 1, 'nadia' => -1] as $handle => $value) {
            $this->reactions->vote($this->profiles[$handle], $this->posts['text_design'], $value);
        }
        foreach (['thabo' => 1, 'precious' => 1] as $handle => $value) {
            $this->reactions->vote($this->profiles[$handle], $this->posts['link_sars'], $value);
        }

        // Likes and votes on comments too.
        $this->reactions->like($this->profiles['precious'], $c2);
        $this->reactions->like($this->profiles['dineo'], $c1);
        $this->reactions->like($this->profiles['thabo'], $c4);
        $this->reactions->vote($this->profiles['karabo'], $c1, 1);
        $this->reactions->like($this->profiles['thabo'], $g1);

        unset($c3, $b1);
    }

    private function comment(string $author, Post $post, string $body, ?string $parentUlid = null): Comment
    {
        return $this->comments->create($this->profiles[$author], $post, [
            'body' => $body,
            'parent_comment_id' => $parentUlid,
        ]);
    }

    // ------------------------------------------------------------------
    // Messaging
    // ------------------------------------------------------------------

    private function seedChallenge(): void
    {
        $challenge = Challenge::create([
            'title' => 'July Hustle Challenge',
            'description' => 'Earn the most XP this month — post, engage and climb the board. Top three win a shout-out from SBH Community.',
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->addDays(21),
            'is_active' => true,
        ]);

        foreach ($this->profiles as $profile) {
            if ($profile->kind === 'personal') {
                $challenge->participants()->attach($profile->id, ['joined_at' => now()->subDays(5)]);
            }
        }
    }

    private function seedMessaging(): void
    {
        // DM 1: thabo <-> lerato (site work), with reactions + read states.
        $dm1 = $this->messaging->findOrCreateDm($this->profiles['thabo'], $this->profiles['lerato']);

        $m1 = $this->dm($dm1, 'thabo', 'Yo Lerato! The booking form is live — first enquiry came in an hour ago. 🎉');
        $m2 = $this->dm($dm1, 'lerato', 'Let’s gooo! 🚀 Told you the deposit field would filter out the tyre-kickers.');
        $m3 = $this->dm($dm1, 'thabo', 'Deposit paid before I even replied. Different world, my friend.');
        $m4 = $this->dm($dm1, 'lerato', 'Next step: automated WhatsApp confirmations. I will scope it this weekend.');

        $this->messaging->addReaction($m1, $this->profiles['lerato'], '🎉');
        $this->messaging->addReaction($m2, $this->profiles['thabo'], '🔥');
        $this->messaging->addReaction($m3, $this->profiles['lerato'], '💪');
        $this->messaging->markRead($dm1, $dm1->participantFor($this->profiles['thabo']), $m4);
        $this->messaging->markRead($dm1, $dm1->participantFor($this->profiles['lerato']), $m3);

        // DM 2: zanele <-> aisha (stock order).
        $dm2 = $this->messaging->findOrCreateDm($this->profiles['zanele'], $this->profiles['aisha']);

        $n1 = $this->dm($dm2, 'zanele', 'Aisha! Do you still stock that rose water in bulk? Clients keep asking about the facial steamer blend. 🌹');
        $n2 = $this->dm($dm2, 'aisha', 'We do! 5L drums, R320 each. I can courier to Cape Town on Thursday.');
        $n3 = $this->dm($dm2, 'zanele', 'Perfect, put me down for two. You are a lifesaver. 🙏');

        $this->messaging->addReaction($n2, $this->profiles['zanele'], '👍');
        $this->messaging->markRead($dm2, $dm2->participantFor($this->profiles['aisha']), $n3);
        $this->messaging->markRead($dm2, $dm2->participantFor($this->profiles['zanele']), $n2);

        // Group: "Joburg Traders" with rules and several members/messages.
        $group = $this->messaging->createGroup(
            $this->profiles['thabo'],
            'Joburg Traders',
            [
                $this->profiles['lerato']->ulid,
                $this->profiles['mandla']->ulid,
                $this->profiles['karabo']->ulid,
                $this->profiles['precious']->ulid,
            ],
            "1. Business talk first, memes second.\n2. No selling to members without asking.\n3. What is shared in the group stays in the group.",
        );

        // Demo group ships pre-approved so its seeded chatter can send.
        $group->update([
            'approval_status' => Conversation::APPROVAL_APPROVED,
            'approved_at' => now(),
        ]);

        $t1 = $this->dm($group, 'thabo', 'Welcome to the trader crew! 🤝 Rule number one is in the group rules. Read them.');
        $t2 = $this->dm($group, 'karabo', 'Already breaking rule 1 with this: anyone need a December promo designed? Special group rate. 😄');
        $this->dm($group, 'precious', 'Reminder that provisional tax is due end of Feb. Do NOT come to me in March with a shoebox of slips.');
        $t4 = $this->dm($group, 'mandla', 'Shooting product photos on Thursday — two slots left if anyone wants storefront shots.');
        $this->dm($group, 'lerato', '@mandla I will take one for the Ubuntu Tech office wall. 📷');

        $this->messaging->addReaction($t1, $this->profiles['lerato'], '🤝');
        $this->messaging->addReaction($t2, $this->profiles['thabo'], '😂');
        $this->messaging->addReaction($t4, $this->profiles['lerato'], '🙋');
        $this->messaging->markRead($group, $group->participantFor($this->profiles['thabo']), $t4);
    }

    private function dm($conversation, string $sender, string $body): Message
    {
        return $this->messaging->sendMessage($conversation, $this->profiles[$sender], ['body' => $body]);
    }

    // ------------------------------------------------------------------
    // Business needs (matchmaking)
    // ------------------------------------------------------------------

    private function seedBusinessNeeds(): void
    {
        $needs = [
            // profile, kind, category slug, description
            ['braai_bros', 'seeking', 'marketing-media', 'Need a social media manager for event-season promos (Oct–Jan).'],
            ['braai_bros', 'offering', 'food-beverage', 'Full catering for corporate events, 30–500 guests, halaal option available.'],
            ['joburg_creatives', 'offering', 'marketing-media', 'Social media management and content design packages for SMEs.'],
            ['joburg_creatives', 'seeking', 'food-beverage', 'Catering partner for monthly client workshop breakfasts (±25 people).'],
            ['ubuntu_tech', 'offering', 'tech-it', 'POS, booking systems and WhatsApp automation for small businesses.'],
            ['durban_spice', 'seeking', 'transport-logistics', 'Reliable cold-chain courier for weekly Durban→Joburg spice shipments.'],
            ['durban_spice', 'offering', 'retail', 'Wholesale spice supply with trade pricing for restaurants and caterers.'],
            ['karoo_logistics', 'offering', 'transport-logistics', 'Scheduled refrigerated freight: Cape Town, Durban and Gauteng lanes.'],
            ['protea_beauty', 'seeking', 'tech-it', 'Online booking system that takes deposits — walk-outs are killing us.'],
            ['protea_beauty', 'seeking', 'marketing-media', 'Before/after content creator for Instagram, 2 shoots a month.'],
        ];

        foreach ($needs as [$handle, $kind, $categorySlug, $description]) {
            $category = BusinessCategory::query()->where('slug', $categorySlug)->firstOrFail();

            $this->needs->create($this->profiles[$handle], [
                'kind' => $kind,
                'business_category_id' => $category->id,
                'description' => $description,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Gamification history
    // ------------------------------------------------------------------

    /**
     * Backfill xp_ledger history over the past 3 weeks so the weekly and
     * all-time leaderboards diverge, then bring xp_total and rank in line.
     */
    private function backfillXpHistory(): void
    {
        $history = [
            // handle => [daysAgo => [action, points][]]
            'thabo' => [20 => 2, 17 => 3, 14 => 1, 9 => 2, 5 => 1, 2 => 2],
            'lerato' => [19 => 3, 15 => 2, 12 => 3, 8 => 2, 3 => 3, 1 => 2],
            'zanele' => [18 => 1, 13 => 2, 6 => 1, 2 => 1],
            'aisha' => [16 => 2, 10 => 1, 4 => 2],
            'karabo' => [20 => 1, 11 => 2, 7 => 1, 1 => 3],
            'nadia' => [14 => 1, 9 => 2, 2 => 2],
            'sipho' => [17 => 1, 8 => 1],
            'precious' => [12 => 2, 5 => 1, 1 => 1],
        ];

        $actions = [
            ['post_published', 10],
            ['comment_created', 5],
            ['like_received', 2],
        ];

        $rows = [];

        foreach ($history as $handle => $days) {
            $profileId = $this->profiles[$handle]->id;

            foreach ($days as $daysAgo => $entryCount) {
                for ($i = 0; $i < $entryCount; $i++) {
                    [$actionKey, $points] = $actions[($daysAgo + $i) % count($actions)];

                    $rows[] = [
                        'profile_id' => $profileId,
                        'action_key' => $actionKey,
                        'points' => $points,
                        'subject_type' => null,
                        'subject_id' => null,
                        'created_at' => now()->subDays($daysAgo)->setTime(8 + ($i * 3) % 12, 30),
                    ];
                }
            }
        }

        DB::table('xp_ledger')->insert($rows);

        // Bring xp_total in line with the full ledger and resolve ranks.
        foreach ($this->profiles as $profile) {
            $total = (int) DB::table('xp_ledger')->where('profile_id', $profile->id)->sum('points');

            $rank = Rank::query()
                ->where('min_xp', '<=', $total)
                ->orderByDesc('min_xp')
                ->first();

            $profile->forceFill([
                'xp_total' => $total,
                'rank_id' => $rank?->id,
            ])->save();
        }
    }

    // ------------------------------------------------------------------
    // Ads
    // ------------------------------------------------------------------

    private function seedAds(): void
    {
        // Active metrics-only campaign promoting a public Braai Bros post.
        $active = $this->campaigns->create($this->profiles['braai_bros'], $this->posts['image_braai'], [
            'duration_days' => 14,
        ]);

        // Completed campaign for Durban Spice (ran its window, closed).
        $completed = $this->campaigns->create($this->profiles['durban_spice'], $this->posts['text_spice_promo'], [
            'duration_days' => 7,
        ]);
        $completed->forceFill([
            'starts_at' => now()->subDays(14),
            'ends_at' => now()->subDays(7),
            'status' => Campaign::STATUS_COMPLETED,
        ])->save();

        // Ad events spread across the last 14 days for both campaigns.
        $viewers = ['sipho', 'mandla', 'dineo', 'precious', 'nadia', 'bongani'];
        $rows = [];

        foreach ([[$active, 0, 13], [$completed, 7, 14]] as [$campaign, $fromDaysAgo, $toDaysAgo]) {
            $impressions = 0;
            $clicks = 0;
            $linkClicks = 0;

            for ($daysAgo = $toDaysAgo; $daysAgo >= $fromDaysAgo; $daysAgo--) {
                // A gentle curve: busier towards the campaign midpoint.
                $daily = 3 + (($daysAgo * 7) % 5);

                for ($i = 0; $i < $daily; $i++) {
                    $isClick = ($i === $daily - 1 && $daysAgo % 2 === 0);
                    $isLinkClick = ! $isClick && $i === 0 && $daysAgo % 3 === 0;
                    $viewer = $this->profiles[$viewers[($daysAgo + $i) % count($viewers)]];

                    $kind = $isClick
                        ? AdEvent::KIND_CLICK
                        : ($isLinkClick ? AdEvent::KIND_LINK_CLICK : AdEvent::KIND_IMPRESSION);

                    $rows[] = [
                        'campaign_id' => $campaign->id,
                        'ad_slot_id' => null,
                        'kind' => $kind,
                        'profile_id' => $viewer->id,
                        'created_at' => now()->subDays($daysAgo)->setTime(10 + ($i % 10), 5 * $i % 60),
                    ];

                    if ($isClick) {
                        $clicks++;
                    } elseif ($isLinkClick) {
                        $linkClicks++;
                    } else {
                        $impressions++;
                    }
                }
            }

            $campaign->forceFill([
                'impressions_count' => $impressions,
                'clicks_count' => $clicks,
                'link_clicks_count' => $linkClicks,
            ])->save();
        }

        DB::table('ad_events')->insert($rows);

        // One active static sponsor slot per placement, with generated 16:9
        // creative. updateOrCreate keeps reseeding idempotent per key.
        foreach ([
            ['demo-right-rail', AdSlot::PLACEMENT_RIGHT_RAIL, 'FNB First Business Account', 'FNB Business', 'FB', '#0B5FFF'],
            ['demo-feed-inline', AdSlot::PLACEMENT_FEED_INLINE, 'Yoco Card Machines', 'Yoco', 'YC', '#00A9E0'],
        ] as [$key, $placement, $name, $sponsor, $initials, $color]) {
            $imagePath = $this->makeSlotImage($key, $initials, $color);

            AdSlot::query()->updateOrCreate(['key' => $key], [
                'placement' => $placement,
                'name' => $name,
                'sponsor_name' => $sponsor,
                'sponsor_url' => 'https://example.co.za/'.$key,
                'image_path' => $imagePath,
                'body' => $name.' — built for South African small businesses.',
                'active' => true,
                'weight' => 3,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Analytics backfill
    // ------------------------------------------------------------------

    /**
     * Backfill post_stats_daily for published demo posts across the last 30
     * days so Insights and Platform Analytics charts have real curves. Today's
     * rows already exist from the live service hooks and are left untouched.
     */
    private function backfillPostStats(): void
    {
        $published = collect($this->posts)
            ->filter(fn (Post $post) => $post->isPublished())
            ->values()
            ->take(22);

        $rows = [];

        foreach ($published as $index => $post) {
            $ageDays = (int) min(30, max(1, now()->diffInDays($post->published_at ?? $post->created_at, true)));
            $peak = 40 + ($index * 13) % 90;

            $totalViews = 0;

            for ($daysAgo = $ageDays; $daysAgo >= 1; $daysAgo--) {
                // Rise-and-decay curve since publication, deterministic per post.
                $t = $ageDays - $daysAgo; // days since publish
                $views = (int) round($peak * ($t + 1) * exp(-$t / 6) / 3) + (($index + $daysAgo) % 4);

                if ($views <= 0) {
                    continue;
                }

                $rows[] = [
                    'post_id' => $post->id,
                    'date' => now()->utc()->subDays($daysAgo)->toDateString(),
                    'views' => $views,
                    'likes' => intdiv($views, 8),
                    'comments' => intdiv($views, 20),
                    'reposts' => intdiv($views, 40),
                    'votes' => intdiv($views, 15),
                ];

                $totalViews += $views;
            }

            $post->forceFill(['views_count' => $post->views_count + $totalViews])->save();
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('post_stats_daily')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Moderation
    // ------------------------------------------------------------------

    private function seedReports(): void
    {
        // A pending report against a post…
        $this->reports->create($this->profiles['pieter'], [
            'reportable_type' => 'post',
            'reportable_ulid' => $this->posts['text_design']->ulid,
            'category' => 'spam',
            'details' => 'This reads like undisclosed advertising for their own agency.',
        ]);

        // …and one against a profile.
        $this->reports->create($this->profiles['bongani'], [
            'reportable_type' => 'profile',
            'reportable_ulid' => $this->profiles['nadia']->ulid,
            'category' => 'other',
            'details' => 'Profile keeps DM-spamming marketing offers after being asked to stop.',
        ]);

        // Both stay pending so the moderation queue shows work.
        Report::query()->update(['status' => Report::STATUS_PENDING]);
    }

    // ------------------------------------------------------------------
    // Asset generation
    // ------------------------------------------------------------------

    /**
     * Generate a brand-ish placeholder image (solid colour block with big
     * initials) with GD and run it through the real MediaService pipeline so
     * WebP conversion, sizing and thumbnails behave exactly like an upload.
     */

    /** Deterministic brand-adjacent avatar color per handle. */
    private function avatarColor(string $handle): string
    {
        $palette = ['4e8a88', '683f59', '5d7868', 'b38236', '575093', '447b78', '89386f', '549c65'];

        return $palette[crc32($handle) % count($palette)];
    }

    /**
     * Generate and attach a profile avatar (and optionally a wide cover),
     * stored directly on the public disk.
     */
    private function attachProfileImages(Profile $profile, bool $withCover = false): void
    {
        $initials = mb_strtoupper(mb_substr($profile->name, 0, 1));
        if (preg_match('/\s(\p{L})/u', $profile->name, $m)) {
            $initials .= mb_strtoupper($m[1]);
        }

        $color = $this->avatarColor($profile->handle);

        $png = $this->generatePng($initials, $color, 512, 512);
        $avatarPath = 'media/avatars/'.$profile->handle.'.png';
        Storage::disk('public')->put($avatarPath, (string) file_get_contents($png));
        @unlink($png);

        $coverPath = null;
        if ($withCover) {
            $png = $this->generatePng($initials, $color, 1500, 500);
            $coverPath = 'media/covers/'.$profile->handle.'.png';
            Storage::disk('public')->put($coverPath, (string) file_get_contents($png));
            @unlink($png);
        }

        $profile->forceFill([
            'avatar_path' => $avatarPath,
            'cover_path' => $coverPath ?? $profile->cover_path,
        ])->save();
    }

    private function makeImage(Profile $profile, string $initials, string $hexColor, int $width = 1200, int $height = 900): Media
    {
        $path = $this->generatePng($initials, $hexColor, $width, $height);

        try {
            return $this->media->storeImage(
                $profile,
                new UploadedFile($path, 'demo.png', 'image/png', null, true),
            );
        } finally {
            @unlink($path);
        }
    }

    /**
     * Generate a 16:9 sponsor creative and store it directly on the public
     * disk (ad slots reference a plain image path, not a Media row).
     */
    private function makeSlotImage(string $key, string $initials, string $hexColor): string
    {
        $png = $this->generatePng($initials, $hexColor, 1280, 720);
        $path = 'media/ad-slots/'.$key.'.png';

        Storage::disk('public')->put($path, (string) file_get_contents($png));
        @unlink($png);

        return $path;
    }

    /**
     * Draw big blocky initials on a solid colour block. Text is drawn small
     * with the GD bitmap font then upscaled, which yields chunky poster-style
     * lettering without needing a TTF font on the host.
     *
     * @return string absolute path of a temporary PNG
     */
    private function generatePng(string $text, string $hexColor, int $width, int $height): string
    {
        [$r, $g, $b] = sscanf(ltrim($hexColor, '#'), '%02x%02x%02x');

        $scale = 8;
        $smallW = (int) max(1, $width / $scale);
        $smallH = (int) max(1, $height / $scale);

        $small = imagecreatetruecolor($smallW, $smallH);
        imagefilledrectangle($small, 0, 0, $smallW, $smallH, imagecolorallocate($small, $r, $g, $b));

        // Subtle darker band along the bottom for a designed look.
        $band = imagecolorallocate($small, (int) ($r * 0.8), (int) ($g * 0.8), (int) ($b * 0.8));
        imagefilledrectangle($small, 0, (int) ($smallH * 0.82), $smallW, $smallH, $band);

        $white = imagecolorallocate($small, 255, 255, 255);
        $font = 5; // 9x15 px per char
        $textW = strlen($text) * imagefontwidth($font);
        imagestring(
            $small,
            $font,
            (int) (($smallW - $textW) / 2),
            (int) (($smallH - imagefontheight($font)) / 2),
            $text,
            $white,
        );

        $full = imagecreatetruecolor($width, $height);
        imagecopyresized($full, $small, 0, 0, 0, 0, $width, $height, $smallW, $smallH);

        $path = (string) tempnam(sys_get_temp_dir(), 'demo_img_');
        imagepng($full, $path);
        imagedestroy($small);
        imagedestroy($full);

        return $path;
    }

    /**
     * Generate a tiny but genuinely playable WAV file (2s 440Hz sine, 8kHz
     * 16-bit mono ≈ 32KB) and store it as ready audio media.
     */
    private function makeAudio(Profile $profile): Media
    {
        $sampleRate = 8000;
        $seconds = 2;
        $samples = $sampleRate * $seconds;

        $data = '';
        for ($i = 0; $i < $samples; $i++) {
            $amplitude = 0.5 * (1 - $i / $samples); // gentle fade-out
            $value = (int) round($amplitude * 32767 * sin(2 * M_PI * 440 * $i / $sampleRate));
            $data .= pack('v', $value & 0xFFFF);
        }

        $byteRate = $sampleRate * 2; // mono, 16-bit
        $header = 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', $sampleRate).pack('V', $byteRate).pack('v', 2).pack('v', 16)
            .'data'.pack('V', strlen($data));

        $ulid = (string) Str::ulid();
        $path = 'media/'.$profile->ulid."/{$ulid}.wav";

        Storage::disk('public')->put($path, $header.$data);

        return Media::query()->create([
            'ulid' => $ulid,
            'profile_id' => $profile->id,
            'type' => Media::TYPE_AUDIO,
            'disk' => 'public',
            'path' => $path,
            'thumb_path' => null,
            'duration_seconds' => $seconds,
            'size_bytes' => strlen($header) + strlen($data),
            'mime' => 'audio/wav',
            'status' => Media::STATUS_READY,
        ]);
    }
}
