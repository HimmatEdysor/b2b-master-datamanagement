<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Free tier with no subscription expiry.',
                'price' => 0,
                'currency' => 'INR',
                'interval' => 'none',
                'limits' => ['users' => 5],
                'features' => ['Up to 5 users', 'Subdomain CRM', 'Community support'],
                'sort_order' => 0,
                'is_featured' => false,
                'is_active' => true,
            ]
        );

        $starter = SubscriptionPlan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'For small teams getting started with B2B CRM.',
                'price' => 0,
                'currency' => 'INR',
                'interval' => 'monthly',
                'limits' => ['users' => 10],
                'features' => ['Up to 10 users', 'Subdomain CRM', 'Email support'],
                'sort_order' => 1,
                'is_featured' => false,
                'is_active' => true,
            ]
        );

        SubscriptionPlan::query()->updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'description' => 'For growing consultancies with more agents and leads.',
                'price' => 4999,
                'currency' => 'INR',
                'interval' => 'monthly',
                'limits' => ['users' => 50],
                'features' => ['Up to 50 users', 'Custom branding', 'Priority support', 'API access'],
                'sort_order' => 2,
                'is_featured' => true,
                'is_active' => true,
            ]
        );

        SubscriptionPlan::query()->updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Dedicated database, custom domain, and SLA.',
                'price' => 14999,
                'currency' => 'INR',
                'interval' => 'monthly',
                'limits' => ['users' => 500],
                'features' => ['Unlimited users', 'White-label domain', 'Dedicated support', 'Custom integrations'],
                'sort_order' => 3,
                'is_featured' => false,
                'is_active' => true,
            ]
        );

        $pages = [
            [
                'slug' => 'about',
                'title' => 'About us',
                'body' => "Guarantee Admit provides multi-tenant B2B CRM software for education consultancies.\n\nEach partner company receives an isolated database, branded portal, and subdomain — managed centrally from the master portal.",
                'show_in_nav' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy policy',
                'body' => 'We respect your privacy. Contact data submitted during registration is used only to provision and support your CRM tenant.',
                'show_in_nav' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of service',
                'body' => 'By registering for a CRM tenant you agree to use the platform in compliance with applicable laws and our acceptable use policy.',
                'show_in_nav' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($pages as $data) {
            Page::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'status' => 'published',
                    'show_in_nav' => $data['show_in_nav'],
                    'sort_order' => $data['sort_order'],
                    'published_at' => now(),
                ]
            );
        }

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'guaranteeadmit'],
            [
                'name' => 'Guarantee Admit',
                'status' => 'active',
                'database_name' => env('SEED_DEFAULT_TENANT_DATABASE', 'b2b_live_database'),
                'database_host' => config('master.tenant_db_host'),
                'database_port' => (int) config('master.tenant_db_port'),
                'database_username' => config('master.tenant_db_username'),
                'database_password' => config('master.tenant_db_password'),
                'brand_name' => 'Guarantee Admit CRM',
                'logo_url' => env('SEED_DEFAULT_TENANT_LOGO', 'https://guaranteeadmit.com/admin_assets/images/icons/logo.svg'),
                'support_email' => 'support@guaranteeadmit.com',
                'subscription_plan_id' => $starter->id,
                'subscription_status' => 'active',
            ]
        );

        foreach (
            [
                ['host' => 'guaranteeadmit.com', 'type' => 'primary', 'is_primary' => true],
                ['host' => 'www.guaranteeadmit.com', 'type' => 'custom', 'is_primary' => false],
            ] as $domain
        ) {
            TenantDomain::query()->updateOrCreate(
                ['host' => $domain['host']],
                [
                    'tenant_id' => $tenant->id,
                    'type' => $domain['type'],
                    'is_primary' => $domain['is_primary'],
                ]
            );
        }

        $this->call(RbacSeeder::class);
    }
}
