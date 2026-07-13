import type { Metadata } from "next";

import { H2, P, PolicyTitle, UL } from "../legal-prose";

export const metadata: Metadata = {
  title: "Terms of Service",
  description: "The terms governing your use of SBH Community App.",
};

const UPDATED = "13 July 2026";

export default function TermsPage() {
  return (
    <>
      <PolicyTitle updated={UPDATED}>Terms of Service</PolicyTitle>

      <P>
        These terms govern your use of SBH Community App. By creating an account you agree to them.
      </P>

      <H2>Your account</H2>
      <UL>
        <li>You must be at least 18 years old and provide accurate information.</li>
        <li>You are responsible for activity on your account and for keeping your password secure.</li>
        <li>You may hold one personal profile and up to three business profiles.</li>
      </UL>

      <H2>Acceptable use</H2>
      <P>You agree not to:</P>
      <UL>
        <li>Post unlawful, hateful, harassing, misleading or infringing content.</li>
        <li>Spam, scrape, or attempt to disrupt or gain unauthorised access to the service.</li>
        <li>Impersonate others or misrepresent your affiliation.</li>
      </UL>
      <P>
        We operate a moderated community. We may remove content, and suspend or terminate accounts,
        that breach these terms.
      </P>

      <H2>Your content</H2>
      <P>
        You keep ownership of what you post. You grant SBH a licence to host and display your
        content so we can operate the service. You are responsible for the content you share.
      </P>

      <H2>Groups, challenges and ads</H2>
      <UL>
        <li>Member-created groups require administrator approval before they become active.</li>
        <li>Challenges and their leaderboards are run by SBH administrators.</li>
        <li>Advertising is managed by SBH; promoted posts are labelled as such.</li>
      </UL>

      <H2>Disclaimers &amp; liability</H2>
      <P>
        The service is provided &quot;as is&quot;. To the extent permitted by law, SBH is not liable
        for indirect or consequential losses arising from your use of the service. Nothing in these
        terms limits rights you have under applicable consumer-protection law.
      </P>

      <H2>Termination</H2>
      <P>
        You may delete your account at any time from Settings. We may suspend or end access for
        breaches of these terms.
      </P>

      <H2>Contact</H2>
      <P>Questions? Email connect@getstoryadvantage.com.</P>
    </>
  );
}
