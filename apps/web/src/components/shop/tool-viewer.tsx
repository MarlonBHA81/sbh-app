"use client";

import { ExternalLink, Wrench } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { ExternalLink as OutboundLink } from "@/components/ui/external-link";
import type { Product } from "@/lib/api/types";
import { fetchOwnedFile } from "@/lib/shop";

/**
 * In-app tool viewer (Shop P3). Renders an owned HTML digital-download product
 * inside a sandboxed iframe (a "calculator"), or launches an external tool URL.
 * The sandbox has NO allow-same-origin, so vendor JS can't reach the app,
 * cookies, or same-origin storage.
 */
export function ToolViewer({ product }: { product: Product }) {
  const [html, setHtml] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isHtmlTool = Boolean(product.is_html_tool);
  const isExternal = !isHtmlTool && Boolean(product.external_url);

  if (!isHtmlTool && !isExternal) return null;

  async function open() {
    setLoading(true);
    setError(null);
    try {
      setHtml(await fetchOwnedFile(product.ulid));
    } catch {
      setError("Couldn't load this tool. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  if (isExternal) {
    return (
      <Button asChild className="h-11 gap-1.5">
        <OutboundLink href={product.external_url}>
          <ExternalLink className="size-4" aria-hidden />
          Launch tool
        </OutboundLink>
      </Button>
    );
  }

  if (html !== null) {
    return (
      <div className="overflow-hidden rounded-(--radius-card) border border-warmgray bg-white shadow-card">
        <iframe
          // sandbox without allow-same-origin isolates untrusted vendor HTML/JS.
          sandbox="allow-scripts allow-forms allow-popups"
          srcDoc={html}
          title={product.title}
          className="h-[70vh] w-full border-0"
        />
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-2">
      <Button type="button" className="h-11 gap-1.5" onClick={open} disabled={loading}>
        <Wrench className="size-4" aria-hidden />
        {loading ? "Loading…" : "Open tool"}
      </Button>
      {error ? (
        <p className="text-[12px] text-red-600" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}
