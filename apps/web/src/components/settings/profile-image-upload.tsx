"use client";

import { Camera, Trash2 } from "lucide-react";
import { useRef, useState } from "react";
import Cropper from "react-easy-crop";
import { toast } from "sonner";

import { cropImageToWebp, type CropArea } from "@/components/composer/crop-image";
import { ProfileAvatar } from "@/components/profile-avatar";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import * as api from "@/lib/api/client";
import type { Profile } from "@/lib/api/types";

type Kind = "avatar" | "cover";

const CONFIG: Record<
  Kind,
  { aspect: number; guidance: string; endpoint: string }
> = {
  avatar: {
    aspect: 1,
    guidance: "Square image · at least 512×512px (JPG, PNG or WebP)",
    endpoint: "avatar",
  },
  cover: {
    aspect: 3,
    guidance: "Wide banner · 1500×500px (3:1) · at least 1200px wide",
    endpoint: "cover",
  },
};

/**
 * Upload / replace / remove a profile avatar or business cover banner.
 * The image is cropped client-side to the required aspect, encoded to WebP,
 * and re-encoded server-side to fixed dimensions.
 */
export function ProfileImageUpload({
  profile,
  kind,
  onUpdated,
}: {
  profile: Profile;
  kind: Kind;
  onUpdated: (profile: Profile) => void;
}) {
  const config = CONFIG[kind];
  const fileRef = useRef<HTMLInputElement>(null);
  const [src, setSrc] = useState<string | null>(null);
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [area, setArea] = useState<CropArea | null>(null);
  const [saving, setSaving] = useState(false);

  const currentUrl = kind === "avatar" ? profile.avatar_url : profile.cover_url;

  function pickFile(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    if (!file.type.startsWith("image/")) {
      toast.error("Please choose an image file.");
      return;
    }
    setCrop({ x: 0, y: 0 });
    setZoom(1);
    setArea(null);
    setSrc(URL.createObjectURL(file));
  }

  async function confirmCrop() {
    if (!area || !src || saving) return;
    setSaving(true);
    try {
      const blob = await cropImageToWebp(src, area);
      const form = new FormData();
      form.append("image", blob, `${kind}.webp`);
      const res = await api.postMultipart<{ data: Profile }>(
        `/api/v1/me/profiles/${profile.ulid}/${config.endpoint}`,
        form,
      );
      onUpdated(res.data);
      toast.success(kind === "avatar" ? "Photo updated" : "Banner updated");
      closeCropper();
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't upload",
      );
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (saving) return;
    setSaving(true);
    try {
      const res = await api.del<{ data: Profile }>(
        `/api/v1/me/profiles/${profile.ulid}/${config.endpoint}`,
      );
      onUpdated(res.data);
      toast.success("Removed");
    } catch (error) {
      toast.error(
        error instanceof api.ApiError ? error.message : "Couldn't remove",
      );
    } finally {
      setSaving(false);
    }
  }

  function closeCropper() {
    if (src) URL.revokeObjectURL(src);
    setSrc(null);
  }

  return (
    <div className="flex flex-col gap-2">
      {kind === "cover" ? (
        <div className="relative aspect-[3/1] w-full overflow-hidden rounded-(--radius-media) border border-warmgray bg-muted">
          {currentUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={currentUrl} alt="" className="size-full object-cover" />
          ) : (
            <div className="flex size-full items-center justify-center text-xs text-text-secondary">
              No banner yet
            </div>
          )}
        </div>
      ) : (
        <ProfileAvatar profile={profile} className="size-20 text-xl" />
      )}

      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-9 gap-2"
          disabled={saving}
          onClick={() => fileRef.current?.click()}
        >
          <Camera className="size-4" aria-hidden />
          {currentUrl
            ? kind === "avatar"
              ? "Change photo"
              : "Change banner"
            : kind === "avatar"
              ? "Add photo"
              : "Add banner"}
        </Button>
        {currentUrl ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-9 gap-2 text-destructive hover:text-destructive"
            disabled={saving}
            onClick={() => void remove()}
          >
            <Trash2 className="size-4" aria-hidden />
            Remove
          </Button>
        ) : null}
      </div>
      <p className="text-xs text-text-secondary">{config.guidance}</p>

      <input
        ref={fileRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={pickFile}
      />

      <Dialog
        open={src !== null}
        onOpenChange={(open) => {
          if (!open && !saving) closeCropper();
        }}
      >
        <DialogContent className="sm:max-w-lg" showCloseButton={false}>
          <DialogHeader>
            <DialogTitle>
              {kind === "avatar" ? "Position your photo" : "Position your banner"}
            </DialogTitle>
            <DialogDescription>
              Drag to reposition, pinch or scroll to zoom.
            </DialogDescription>
          </DialogHeader>
          <div
            className={
              kind === "avatar"
                ? "relative h-72 w-full overflow-hidden rounded-lg bg-muted"
                : "relative aspect-[3/1] w-full overflow-hidden rounded-lg bg-muted"
            }
          >
            {src ? (
              <Cropper
                image={src}
                crop={crop}
                zoom={zoom}
                aspect={config.aspect}
                cropShape={kind === "avatar" ? "round" : "rect"}
                showGrid={kind === "cover"}
                onCropChange={setCrop}
                onZoomChange={setZoom}
                onCropComplete={(_, pixels) => setArea(pixels)}
              />
            ) : null}
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              className="h-11"
              disabled={saving}
              onClick={closeCropper}
            >
              Cancel
            </Button>
            <Button
              type="button"
              className="h-11"
              disabled={saving || !area}
              onClick={() => void confirmCrop()}
            >
              {saving ? "Saving…" : "Save"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
