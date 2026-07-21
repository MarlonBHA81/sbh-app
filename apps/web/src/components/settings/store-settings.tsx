"use client";

import { CheckCircle2, ExternalLink, Trash2, Upload } from "lucide-react";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import * as api from "@/lib/api/client";
import type { Product, ProductType, Store } from "@/lib/api/types";
import { formatPrice } from "@/lib/shop";

interface Earnings {
  orders_count: number;
  gross_cents: number;
  earnings_cents: number;
  currency: string;
}

/** Vendor store management (Shop P1/P2): brand a store, manage products, sales. */
export function StoreSettings() {
  const [store, setStore] = useState<Store | null | undefined>(undefined);
  const [products, setProducts] = useState<Product[]>([]);
  const [earnings, setEarnings] = useState<Earnings | null>(null);

  useEffect(() => {
    let cancelled = false;
    async function load() {
      try {
        const s = (await api.get<{ data: Store | null }>("/api/v1/me/store"))
          .data;
        if (cancelled) return;
        setStore(s);
        if (s) {
          const p = (await api.get<{ data: Product[] }>("/api/v1/me/store/products")).data;
          if (!cancelled) setProducts(p);
          try {
            const e = (await api.get<{ data: Earnings }>("/api/v1/me/store/orders")).data;
            if (!cancelled) setEarnings(e);
          } catch {
            // Earnings are best-effort; ignore if unavailable.
          }
        }
      } catch {
        if (!cancelled) setStore(null);
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  if (store === undefined) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Store</CardTitle>
        <CardDescription>
          Open a branded storefront and sell courses, downloads and services.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <StoreForm store={store} onSaved={setStore} />
        {store ? (
          <>
            <Link
              href={`/shop/${store.slug}`}
              className="flex w-fit items-center gap-1 text-[13px] font-medium text-teal-text hover:underline"
            >
              View your storefront
              <ExternalLink className="size-3.5" aria-hidden />
            </Link>
            {earnings ? <EarningsSummary earnings={earnings} /> : null}
            <ProductManager
              products={products}
              onChange={setProducts}
            />
          </>
        ) : null}
      </CardContent>
    </Card>
  );
}

function StoreForm({
  store,
  onSaved,
}: {
  store: Store | null;
  onSaved: (s: Store) => void;
}) {
  const [name, setName] = useState(store?.name ?? "");
  const [tagline, setTagline] = useState(store?.tagline ?? "");
  const [brand, setBrand] = useState(store?.brand_color ?? "#4e8a88");
  const [busy, setBusy] = useState(false);

  async function save() {
    if (name.trim() === "" || busy) return;
    setBusy(true);
    try {
      const res = await api.post<{ data: Store }>("/api/v1/me/store", {
        name: name.trim(),
        tagline: tagline.trim() || null,
        brand_color: brand,
      });
      onSaved(res.data);
      toast.success(store ? "Store updated" : "Store opened");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't save store",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-2 rounded-lg border p-3">
      <Input
        value={name}
        onChange={(e) => setName(e.target.value)}
        placeholder="Store name"
        className="h-11"
      />
      <Input
        value={tagline}
        onChange={(e) => setTagline(e.target.value)}
        placeholder="Tagline (optional)"
        className="h-11"
      />
      <label className="flex items-center gap-2 text-sm text-text-secondary">
        Brand colour
        <input
          type="color"
          value={brand}
          onChange={(e) => setBrand(e.target.value)}
          aria-label="Brand colour"
          className="size-8 rounded border"
        />
      </label>
      <Button
        type="button"
        className="h-11 sm:self-start"
        disabled={busy || name.trim() === ""}
        onClick={() => void save()}
      >
        {store ? "Save store" : "Open store"}
      </Button>
    </div>
  );
}

function EarningsSummary({ earnings }: { earnings: Earnings }) {
  return (
    <div className="grid grid-cols-3 gap-2 rounded-lg border p-3">
      <Stat label="Sales" value={String(earnings.orders_count)} />
      <Stat
        label="Gross"
        value={formatPrice(earnings.gross_cents, earnings.currency)}
      />
      <Stat
        label="Your earnings"
        value={formatPrice(earnings.earnings_cents, earnings.currency)}
      />
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col">
      <span className="text-sm font-semibold text-text-primary">{value}</span>
      <span className="text-[11px] text-text-secondary">{label}</span>
    </div>
  );
}

/** Upload the deliverable file for a digital product (private disk). */
function FileUpload({
  product,
  onUploaded,
}: {
  product: Product;
  onUploaded: (ulid: string) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);

  async function onPick(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setBusy(true);
    try {
      const form = new FormData();
      form.append("file", file);
      await api.postMultipart(
        `/api/v1/me/store/products/${product.ulid}/file`,
        form,
      );
      onUploaded(product.ulid);
      toast.success("File uploaded");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Upload failed",
      );
    } finally {
      setBusy(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  return (
    <>
      <input
        ref={inputRef}
        type="file"
        className="hidden"
        onChange={(e) => void onPick(e)}
      />
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        disabled={busy}
        className="flex shrink-0 items-center gap-1 text-[12px] font-medium text-teal-text hover:underline disabled:opacity-60"
      >
        {product.has_file ? (
          <>
            <CheckCircle2 className="size-3.5" aria-hidden />
            {busy ? "Uploading…" : "Replace file"}
          </>
        ) : (
          <>
            <Upload className="size-3.5" aria-hidden />
            {busy ? "Uploading…" : "Upload file"}
          </>
        )}
      </button>
    </>
  );
}

const TYPES: ProductType[] = ["digital_download", "course", "service"];

function ProductManager({
  products,
  onChange,
}: {
  products: Product[];
  onChange: (p: Product[]) => void;
}) {
  const [title, setTitle] = useState("");
  const [type, setType] = useState<ProductType>("digital_download");
  const [description, setDescription] = useState("");
  const [rands, setRands] = useState("");
  const [busy, setBusy] = useState(false);

  async function add() {
    if (title.trim() === "" || description.trim() === "" || busy) return;
    setBusy(true);
    try {
      const priceCents = rands.trim() === "" ? null : Math.round(Number(rands) * 100);
      const res = await api.post<{ data: Product }>("/api/v1/me/store/products", {
        type,
        title: title.trim(),
        description: description.trim(),
        price_cents: Number.isFinite(priceCents as number) ? priceCents : null,
        is_published: true,
      });
      onChange([res.data, ...products]);
      setTitle("");
      setDescription("");
      setRands("");
      toast.success("Product added");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't add product",
      );
    } finally {
      setBusy(false);
    }
  }

  async function remove(ulid: string) {
    try {
      await api.del(`/api/v1/me/store/products/${ulid}`);
      onChange(products.filter((p) => p.ulid !== ulid));
    } catch {
      toast.error("Couldn't remove product");
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <h3 className="text-sm font-medium text-text-primary">Products</h3>

      <ul className="flex flex-col gap-2">
        {products.map((p) => (
          <li
            key={p.ulid}
            className="flex min-h-11 items-center gap-3 rounded-lg border p-3"
          >
            <span className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-sm font-medium text-text-primary">
                {p.title}
              </span>
              <span className="text-xs text-text-secondary">
                {formatPrice(p.price_cents, p.currency)}
                {p.is_published ? "" : " · draft"}
              </span>
            </span>
            {p.type === "course" ? (
              <Link
                href={`/settings/courses/${p.ulid}`}
                className="shrink-0 text-[12px] font-medium text-teal-text hover:underline"
              >
                Curriculum
              </Link>
            ) : null}
            {p.type === "digital_download" || p.type === "course" ? (
              <FileUpload
                product={p}
                onUploaded={(ulid) =>
                  onChange(
                    products.map((x) =>
                      x.ulid === ulid ? { ...x, has_file: true } : x,
                    ),
                  )
                }
              />
            ) : null}
            <button
              type="button"
              aria-label={`Remove ${p.title}`}
              onClick={() => void remove(p.ulid)}
              className="flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-destructive"
            >
              <Trash2 className="size-4" aria-hidden />
            </button>
          </li>
        ))}
        {products.length === 0 ? (
          <li className="text-sm text-text-secondary">No products yet.</li>
        ) : null}
      </ul>

      <div className="flex flex-col gap-2 rounded-lg border p-3">
        <Input
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="Product title"
          className="h-11"
        />
        <div className="flex gap-2">
          <Select value={type} onValueChange={(v) => setType(v as ProductType)}>
            <SelectTrigger className="h-11 flex-1">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {TYPES.map((t) => (
                <SelectItem key={t} value={t}>
                  {t.replace("_", " ")}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Input
            value={rands}
            onChange={(e) => setRands(e.target.value)}
            placeholder="Price (R)"
            inputMode="decimal"
            className="h-11 w-28"
          />
        </div>
        <Textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Short description"
          rows={2}
        />
        <Button
          type="button"
          className="h-11 sm:self-start"
          disabled={busy || title.trim() === "" || description.trim() === ""}
          onClick={() => void add()}
        >
          Add product
        </Button>
      </div>
    </div>
  );
}
