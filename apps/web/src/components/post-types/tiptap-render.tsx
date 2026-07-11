import { Fragment, type ReactNode } from "react";

import type { TiptapDoc, TiptapMark, TiptapNode } from "@/lib/api/types";
import { cn } from "@/lib/utils";

/**
 * Pure Tiptap-JSON → React renderer. Deliberately carries no @tiptap/*
 * dependency at render time (keeps feed/detail bundles lean); it walks the
 * whitelisted node/mark tree the backend already validates. Anything outside
 * the whitelist is skipped.
 */

function applyMarks(text: string, marks: TiptapMark[] | undefined, key: number): ReactNode {
  if (!marks || marks.length === 0) return <Fragment key={key}>{text}</Fragment>;

  return marks.reduce<ReactNode>((node, mark) => {
    switch (mark.type) {
      case "bold":
        return <strong>{node}</strong>;
      case "italic":
        return <em>{node}</em>;
      case "strike":
        return <s>{node}</s>;
      case "code":
        return (
          <code className="rounded bg-muted px-1 py-0.5 font-mono text-[0.85em]">
            {node}
          </code>
        );
      case "link": {
        const href =
          typeof mark.attrs?.href === "string" ? mark.attrs.href : "#";
        return (
          <a
            href={href}
            target="_blank"
            rel="noopener noreferrer nofollow"
            className="text-primary underline underline-offset-4"
            onClick={(event) => event.stopPropagation()}
          >
            {node}
          </a>
        );
      }
      default:
        return node;
    }
  }, <Fragment key={key}>{text}</Fragment>);
}

function renderChildren(nodes: TiptapNode[] | undefined): ReactNode[] {
  if (!nodes) return [];
  return nodes.map((child, index) => renderNode(child, index));
}

function renderNode(node: TiptapNode, key: number): ReactNode {
  switch (node.type) {
    case "text":
      return applyMarks(node.text ?? "", node.marks, key);

    case "paragraph":
      return (
        <p key={key} className="my-2 leading-relaxed">
          {renderChildren(node.content)}
        </p>
      );

    case "heading": {
      const level = Number(node.attrs?.level) === 3 ? 3 : 2;
      const Tag = level === 3 ? "h3" : "h2";
      return (
        <Tag
          key={key}
          className={cn(
            "mt-4 mb-2 font-semibold",
            level === 3 ? "text-base" : "text-lg",
          )}
        >
          {renderChildren(node.content)}
        </Tag>
      );
    }

    case "bulletList":
      return (
        <ul key={key} className="my-2 list-disc pl-5">
          {renderChildren(node.content)}
        </ul>
      );

    case "orderedList":
      return (
        <ol key={key} className="my-2 list-decimal pl-5">
          {renderChildren(node.content)}
        </ol>
      );

    case "listItem":
      return (
        <li key={key} className="my-1">
          {renderChildren(node.content)}
        </li>
      );

    case "blockquote":
      return (
        <blockquote
          key={key}
          className="my-3 border-l-2 border-border pl-3 text-muted-foreground italic"
        >
          {renderChildren(node.content)}
        </blockquote>
      );

    case "codeBlock":
      return (
        <pre
          key={key}
          className="my-3 overflow-x-auto rounded-md bg-muted p-3 font-mono text-sm"
        >
          <code>{renderChildren(node.content)}</code>
        </pre>
      );

    case "horizontalRule":
      return <hr key={key} className="my-4 border-border" />;

    case "hardBreak":
      return <br key={key} />;

    case "image": {
      const src = typeof node.attrs?.src === "string" ? node.attrs.src : null;
      if (!src) return null;
      const alt = typeof node.attrs?.alt === "string" ? node.attrs.alt : "";
      return (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          key={key}
          src={src}
          alt={alt}
          loading="lazy"
          className="my-3 max-w-full rounded-lg"
        />
      );
    }

    default:
      // Unknown node: still render whitelisted descendants if any.
      return node.content ? (
        <Fragment key={key}>{renderChildren(node.content)}</Fragment>
      ) : null;
  }
}

export function TiptapRenderer({
  doc,
  className,
}: {
  doc: TiptapDoc | null | undefined;
  className?: string;
}) {
  if (!doc || doc.type !== "doc") return null;
  return (
    <div className={cn("text-[15px]", className)}>
      {renderChildren(doc.content)}
    </div>
  );
}
