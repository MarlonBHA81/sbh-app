import { ProductView } from "@/components/shop/product-view";

export default async function ProductPage({
  params,
}: {
  params: Promise<{ slug: string; ulid: string }>;
}) {
  const { slug, ulid } = await params;
  return <ProductView slug={slug} ulid={ulid} />;
}
