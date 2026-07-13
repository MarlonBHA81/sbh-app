import type { Metadata } from "next";

import { H2, P, PolicyTitle, UL } from "../legal-prose";

export const metadata: Metadata = {
  title: "Cookie Policy",
  description:
    "How SBH Community App uses cookies and similar technologies, and how to control them.",
};

const UPDATED = "13 July 2026";

export default function CookiePage() {
  return (
    <>
      <PolicyTitle updated={UPDATED}>Cookie Policy</PolicyTitle>

      <P>
        This policy explains how SBH Community App uses cookies and similar local-storage
        technologies, and the choices you have. It should be read together with our Privacy Policy.
      </P>

      <H2>What cookies are</H2>
      <P>
        Cookies are small files stored on your device. Similar technologies (like
        <code> localStorage</code>) let a site remember information between visits. We use both.
      </P>

      <H2>Categories we use</H2>
      <UL>
        <li>
          <strong>Strictly necessary</strong> — required for the app to work: your login session
          (Sanctum), CSRF protection, your chosen language, and your theme (light/dark/system).
          These are always on; the app cannot function without them, so they don&apos;t require
          consent.
        </li>
        <li>
          <strong>Functional</strong> — remember preferences such as low-data mode and which feed
          tab you last used, to improve your experience.
        </li>
        <li>
          <strong>Analytics</strong> — if enabled, help us understand aggregate, de-identified usage
          so we can improve SBH. These are only set with your consent.
        </li>
      </UL>
      <P>
        We do <strong>not</strong> use advertising or cross-site tracking cookies. Ads shown in SBH
        are served from our own systems and do not track you across other websites.
      </P>

      <H2>Managing your choices</H2>
      <P>
        When you first visit, we ask you to accept or reject non-essential cookies. You can change
        your choice any time from the cookie banner or via Settings. You can also block or delete
        cookies in your browser settings, though strictly-necessary cookies are needed to stay
        signed in.
      </P>

      <H2>Changes</H2>
      <P>We may update this policy; the &quot;last updated&quot; date reflects the latest version.</P>
    </>
  );
}
