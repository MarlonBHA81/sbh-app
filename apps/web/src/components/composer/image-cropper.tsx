"use client";

import { useState } from "react";
import Cropper from "react-easy-crop";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { cn } from "@/lib/utils";

import { cropImageToWebp, type CropArea } from "./crop-image";

type AspectPreset = "square" | "wide" | "free";

const PRESETS: { value: AspectPreset; label: string }[] = [
  { value: "square", label: "1:1" },
  { value: "wide", label: "16:9" },
  { value: "free", label: "Free" },
];

/**
 * Crop dialog around react-easy-crop. "Free" uses the image's natural
 * aspect ratio (the whole image fits the crop box).
 */
export function ImageCropper({
  src,
  onCancel,
  onCropped,
}: {
  src: string;
  onCancel: () => void;
  onCropped: (blob: Blob) => void;
}) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [preset, setPreset] = useState<AspectPreset>("square");
  const [naturalAspect, setNaturalAspect] = useState(1);
  const [area, setArea] = useState<CropArea | null>(null);
  const [saving, setSaving] = useState(false);

  const aspect =
    preset === "square" ? 1 : preset === "wide" ? 16 / 9 : naturalAspect;

  async function confirm() {
    if (!area || saving) return;
    setSaving(true);
    try {
      const blob = await cropImageToWebp(src, area);
      onCropped(blob);
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : "Couldn't crop the image",
      );
      setSaving(false);
    }
  }

  return (
    <Dialog
      open
      onOpenChange={(open) => {
        if (!open && !saving) onCancel();
      }}
    >
      <DialogContent className="sm:max-w-lg" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>Crop photo</DialogTitle>
          <DialogDescription>
            Drag to reposition, pinch or scroll to zoom.
          </DialogDescription>
        </DialogHeader>
        <div className="relative h-72 w-full overflow-hidden rounded-lg bg-muted sm:h-80">
          <Cropper
            image={src}
            crop={crop}
            zoom={zoom}
            aspect={aspect}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={(_, croppedAreaPixels) =>
              setArea(croppedAreaPixels)
            }
            onMediaLoaded={(mediaSize) => {
              if (mediaSize.naturalHeight > 0) {
                setNaturalAspect(
                  mediaSize.naturalWidth / mediaSize.naturalHeight,
                );
              }
            }}
          />
        </div>
        <div
          className="flex gap-2"
          role="radiogroup"
          aria-label="Aspect ratio"
        >
          {PRESETS.map(({ value, label }) => (
            <Button
              key={value}
              type="button"
              role="radio"
              aria-checked={preset === value}
              variant={preset === value ? "default" : "outline"}
              size="sm"
              className={cn("h-11 flex-1")}
              onClick={() => setPreset(value)}
            >
              {label}
            </Button>
          ))}
        </div>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11"
            disabled={saving}
            onClick={onCancel}
          >
            Skip photo
          </Button>
          <Button
            type="button"
            className="h-11"
            disabled={saving || !area}
            onClick={() => void confirm()}
          >
            {saving ? "Cropping…" : "Use photo"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default ImageCropper;
