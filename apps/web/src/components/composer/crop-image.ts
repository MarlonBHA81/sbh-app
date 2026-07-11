export interface CropArea {
  x: number;
  y: number;
  width: number;
  height: number;
}

const MAX_DIMENSION = 2048;

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.addEventListener("load", () => resolve(image));
    image.addEventListener("error", () =>
      reject(new Error("Couldn't load image")),
    );
    image.src = src;
  });
}

/** Crop `src` to `area` on a canvas and encode as a webp blob. */
export async function cropImageToWebp(
  src: string,
  area: CropArea,
): Promise<Blob> {
  const image = await loadImage(src);

  const scale = Math.min(
    1,
    MAX_DIMENSION / Math.max(area.width, area.height),
  );
  const canvas = document.createElement("canvas");
  canvas.width = Math.max(1, Math.round(area.width * scale));
  canvas.height = Math.max(1, Math.round(area.height * scale));

  const context = canvas.getContext("2d");
  if (!context) throw new Error("Canvas is not supported in this browser");

  context.drawImage(
    image,
    area.x,
    area.y,
    area.width,
    area.height,
    0,
    0,
    canvas.width,
    canvas.height,
  );

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => {
        if (blob) resolve(blob);
        else reject(new Error("Couldn't encode image"));
      },
      "image/webp",
      0.85,
    );
  });
}
