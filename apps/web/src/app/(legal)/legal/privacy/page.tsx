import type { Metadata } from "next";

import { H2, P, PolicyTitle, UL } from "../legal-prose";

export const metadata: Metadata = {
  title: "Privacy Policy",
  description:
    "How SBH Community App collects, uses and protects your personal information, and your rights under the GDPR and POPIA.",
};

const UPDATED = "13 July 2026";

export default function PrivacyPage() {
  return (
    <>
      <PolicyTitle updated={UPDATED}>Privacy Policy</PolicyTitle>

      <P>
        SBH Community App (&quot;SBH&quot;, &quot;we&quot;, &quot;us&quot;) helps small business
        owners connect, share and grow. This policy explains what personal information we collect,
        why, how we protect it, and the rights you have under the EU General Data Protection
        Regulation (GDPR) and South Africa&apos;s Protection of Personal Information Act (POPIA).
      </P>

      <H2>Who is responsible for your data</H2>
      <P>
        SBH Community App is the responsible party / data controller. For any privacy request,
        contact our Information Officer at{" "}
        <a href="mailto:connect@getstoryadvantage.com" className="text-teal-text hover:underline">
          connect@getstoryadvantage.com
        </a>
        .
      </P>

      <H2>What we collect</H2>
      <UL>
        <li><strong>Account data</strong> — your email, name, password (stored hashed), locale and timezone.</li>
        <li><strong>Profile data</strong> — handle, display name, bio, avatar/cover images, business category, website and social links you choose to add.</li>
        <li><strong>Content you create</strong> — posts, comments, messages, media, reactions, polls and event RSVPs.</li>
        <li><strong>Location</strong> — only if you opt in to the &quot;Nearby&quot; features; used to show local content. You can turn this off at any time.</li>
        <li><strong>Usage &amp; device data</strong> — basic technical logs (IP address, approximate location, device/browser) needed to run and secure the service.</li>
        <li><strong>Social sign-in</strong> — if you sign in with Google, Facebook or X, we receive your basic profile and email from that provider.</li>
      </UL>

      <H2>Why we use it (lawful basis)</H2>
      <UL>
        <li><strong>To provide the service</strong> — performance of our contract with you (account, feeds, messaging).</li>
        <li><strong>To keep the community safe</strong> — our legitimate interest and legal obligations (moderation, abuse prevention, security).</li>
        <li><strong>With your consent</strong> — location features, optional AI-assisted suggestions, web-push notifications and any non-essential cookies.</li>
      </UL>

      <H2>AI processing</H2>
      <P>
        If enabled by our administrators, limited text (a draft post, or content you report) may be
        sent to a third-party AI provider (Anthropic or OpenAI) to suggest topics or assist
        moderation. This is optional, minimal, and never used to train those providers&apos; models
        under our configuration.
      </P>

      <H2>Who we share it with</H2>
      <P>
        We do not sell your personal information. We share it only with service providers that help
        us run SBH — hosting, email delivery (e.g. Brevo/Resend), and the optional AI providers
        above — each bound to protect it. Public content (public posts, public profiles) is, by
        design, visible to anyone.
      </P>

      <H2>International transfers</H2>
      <P>
        Some providers process data outside your country. Where that happens, we rely on appropriate
        safeguards (such as standard contractual clauses) as required by the GDPR and POPIA.
      </P>

      <H2>How long we keep it</H2>
      <P>
        We keep your data while your account is active. When you delete your account we permanently
        remove your personal data and content, except where we must retain limited records to meet a
        legal obligation.
      </P>

      <H2>Your rights</H2>
      <P>
        Under the GDPR and POPIA you can access, correct, export or delete your data, object to or
        restrict certain processing, and withdraw consent. You can:
      </P>
      <UL>
        <li><strong>Export your data</strong> — Settings → Privacy &amp; data → &quot;Download my data&quot;.</li>
        <li><strong>Delete your account</strong> — Settings → Privacy &amp; data → &quot;Delete account&quot;.</li>
        <li><strong>Contact us</strong> for any other request at connect@getstoryadvantage.com.</li>
      </UL>
      <P>
        You also have the right to lodge a complaint with a supervisory authority — the Information
        Regulator (South Africa) or your local EU data-protection authority.
      </P>

      <H2>Security</H2>
      <P>
        We protect your data with encryption in transit (HTTPS), hashed passwords, access controls
        and rate limiting. No system is perfectly secure, but we work to keep your information safe.
      </P>

      <H2>Children</H2>
      <P>SBH is not intended for anyone under 18. We do not knowingly collect data from children.</P>

      <H2>Changes</H2>
      <P>
        We may update this policy; we&apos;ll revise the &quot;last updated&quot; date and, for
        material changes, notify you in the app.
      </P>
    </>
  );
}
