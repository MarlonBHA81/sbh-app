"use client";

import { useEffect, useRef, useState } from "react";

/**
 * HLS video player (ask #4). Uses the browser's native HLS support when
 * available (Safari / iOS — and the future native app's WebView), otherwise
 * loads hls.js. The `.m3u8` playback URL comes from the streaming provider.
 */
export function HlsPlayer({
  src,
  poster,
}: {
  src: string;
  poster?: string;
}) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;
    setError(false);

    // Native HLS (Safari / iOS): just set the source.
    if (video.canPlayType("application/vnd.apple.mpegurl")) {
      video.src = src;
      return;
    }

    // Everyone else: attach hls.js.
    let destroyed = false;
    let hls: import("hls.js").default | null = null;

    import("hls.js")
      .then(({ default: Hls }) => {
        if (destroyed) return;
        if (!Hls.isSupported()) {
          setError(true);
          return;
        }
        hls = new Hls({ enableWorker: true, lowLatencyMode: true });
        hls.loadSource(src);
        hls.attachMedia(video);
        hls.on(Hls.Events.ERROR, (_e, data) => {
          if (data.fatal) setError(true);
        });
      })
      .catch(() => {
        if (!destroyed) setError(true);
      });

    return () => {
      destroyed = true;
      hls?.destroy();
    };
  }, [src]);

  if (error) {
    return (
      <div className="flex aspect-video w-full items-center justify-center rounded-lg bg-black text-center text-sm text-white/70">
        This stream can&apos;t play in your browser. Try Safari, or the app.
      </div>
    );
  }

  return (
    <video
      ref={videoRef}
      controls
      autoPlay
      playsInline
      poster={poster}
      className="aspect-video w-full rounded-lg bg-black"
    />
  );
}
