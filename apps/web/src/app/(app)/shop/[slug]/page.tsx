import { StorefrontView } from "@/components/shop/storefront-view";

export default async function StorefrontPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  return <StorefrontView slug={slug} />;
}
