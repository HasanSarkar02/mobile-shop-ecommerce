<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaticPageStatus;
use App\Models\Faq;
use App\Models\StaticPage;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Production trust baseline for every new tenant: the policy pages the PDP
 * policy strip and footer already resolve against (ProductController::
 * POLICY_SLUGS + footer groups) and the general FAQ entries rendered at
 * /faq. Idempotent — safe to run repeatedly and on existing tenants.
 */
class TrustContentSeeder extends Seeder
{
    public function run(?Tenant $tenant = null): void
    {
        $tenants = $tenant !== null ? collect([$tenant]) : Tenant::query()->get();

        $tenants->each(function (Tenant $tenant): void {
            // Tenant-scoped models require an active tenancy context, same as
            // DatabaseSeeder's provisioning block.
            app(Tenancy::class)->set($tenant);
            $this->seedPolicies($tenant);
            $this->seedFaqs($tenant);
        });

        app(Tenancy::class)->set(null);
    }

    private function seedPolicies(Tenant $tenant): void
    {
        $policies = [
            'delivery-policy' => [
                'title' => 'Delivery Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'Delivery areas, timeframes, and shipping charges.',
                'content' => '<h2>Delivery Areas</h2><p>We deliver nationwide across Bangladesh. Inside Dhaka, orders typically arrive within 1-2 working days; outside Dhaka, within 2-5 working days.</p><h2>Charges</h2><p>Delivery charges are shown at checkout before you place your order. Some campaigns include free delivery.</p><h2>Delivery Confirmation</h2><p>Our team may call you to confirm the order before dispatch. Please make sure your phone number is reachable.</p>',
            ],
            'warranty-policy' => [
                'title' => 'Warranty Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'Warranty coverage and claim process.',
                'content' => '<h2>Coverage</h2><p>Products with an official warranty are covered per the manufacturer terms shown on the product page. Warranty covers manufacturing defects only.</p><h2>Claims</h2><p>To claim, contact us with your invoice number and the product serial/IMEI. Physical damage, water damage, and unauthorized repairs void the warranty.</p>',
            ],
            'return-policy' => [
                'title' => 'Return Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'How to return a product.',
                'content' => '<h2>Eligibility</h2><p>Products can be returned within 7 days of delivery if unused, with all original packaging and accessories included.</p><h2>Process</h2><p>Contact our support team with your order number. Approved returns are collected or dropped off at our outlet, and refunds are issued per our refund policy.</p>',
            ],
            'exchange-policy' => [
                'title' => 'Exchange Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'Product exchange rules.',
                'content' => '<h2>Exchange Window</h2><p>Defective-on-arrival products can be exchanged within 7 days of delivery for the same model, subject to stock availability.</p><h2>Conditions</h2><p>The product must include all original accessories and packaging. Serial/IMEI must match our records.</p>',
            ],
            'refund-policy' => [
                'title' => 'Refund Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'Refund timelines and methods.',
                'content' => '<h2>Refund Timeline</h2><p>Approved refunds are processed within 3-7 working days of receiving the returned product.</p><h2>Refund Method</h2><p>Refunds are issued via bKash/Nagad or bank transfer to the account used for payment. Cash-on-delivery advances are refunded the same way.</p>',
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'footer_group' => 'About',
                'meta_description' => 'How we handle your personal data.',
                'content' => '<h2>Data We Collect</h2><p>We collect only what is needed to fulfil your order: name, contact details, and delivery address.</p><h2>What We Never Do</h2><p>We never sell your data. We share it only with delivery partners as required to complete your shipment.</p>',
            ],
            'emi-payment-policy' => [
                'title' => 'EMI & Payment Policy',
                'footer_group' => 'Payments',
                'meta_description' => 'Cash on delivery, mobile payments, bank transfer, and EMI plans.',
                'content' => '<h2>Payment Methods</h2><p>We accept Cash on Delivery (COD), bKash/Nagad/Rocket send-money, bank transfer, and online card payments where available.</p><h2>Cash on Delivery</h2><p>Pay the full amount in cash when your order arrives. Our rider will confirm the amount before handover.</p><h2>Manual Mobile Payments</h2><p>Send the exact amount to the number shown at checkout, then submit your Transaction ID on the confirmation page. Orders are confirmed after verification.</p><h2>EMI</h2><p>EMI plans are available on eligible products through partner banks. Tenure and interest are displayed on the product page.</p>',
            ],
            'pre-order-policy' => [
                'title' => 'Pre-Order Policy',
                'footer_group' => 'Customer Service',
                'meta_description' => 'How pre-orders work: payment, ETA, and split shipments.',
                'content' => '<h2>How Pre-Orders Work</h2><p>Pre-order items are paid in full at checkout using any supported payment method, including COD (paid on delivery). Your payment reserves your unit from the incoming stock allocation.</p><h2>Expected Availability</h2><p>Each pre-order shows an estimated availability date at checkout. This date is an estimate and may shift slightly based on supplier shipments.</p><h2>Split Shipments</h2><p>If your cart mixes in-stock and pre-order items, in-stock items ship first. Pre-order items ship separately around their expected availability date at no extra shipping cost.</p><h2>Cancellation</h2><p>You may cancel a pre-order any time before it ships. Contact support with your order number.</p>',
            ],
        ];

        foreach ($policies as $slug => $data) {
            StaticPage::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'status' => StaticPageStatus::Published,
                    'show_in_footer' => true,
                    'footer_group' => $data['footer_group'],
                    'meta_title' => $data['title'].' - '.$tenant->name,
                    'meta_description' => $data['meta_description'],
                ],
            );
        }
    }

    private function seedFaqs(Tenant $tenant): void
    {
        $faqs = [
            ['question' => 'How long does delivery take?', 'answer' => 'Inside Dhaka: 1-2 working days. Outside Dhaka: 2-5 working days. You will receive a confirmation call before dispatch.', 'sort_order' => 1],
            ['question' => 'What payment methods do you accept?', 'answer' => 'Cash on Delivery, bKash/Nagad/Rocket send-money, bank transfer, and online card payments. All options appear at checkout.', 'sort_order' => 2],
            ['question' => 'Can I return or exchange a product?', 'answer' => 'Yes — within 7 days of delivery if unused and complete with packaging. See our Return and Exchange policies for details.', 'sort_order' => 3],
            ['question' => 'How do pre-orders work?', 'answer' => 'Pre-order items are paid in full at checkout and ship around the expected availability date shown on the product page. In-stock items in the same cart ship first.', 'sort_order' => 4],
            ['question' => 'Is EMI available?', 'answer' => 'Yes, on eligible products through partner banks. Available tenures and monthly instalments are shown on the product page.', 'sort_order' => 5],
            ['question' => 'Are your products genuine?', 'answer' => 'Yes. We source officially and honour manufacturer warranties on warranted items. Official-import badges are shown where applicable.', 'sort_order' => 6],
        ];

        foreach ($faqs as $faq) {
            Faq::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => null, 'question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
