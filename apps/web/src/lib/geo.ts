/**
 * Geohash encoding (standard base-32 algorithm, no dependency).
 *
 * A geohash interleaves latitude/longitude bits and maps each 5-bit group to a
 * base-32 character, so a shared prefix means geographic proximity. We use it
 * (precision 4 ≈ a ~20 km cell) to bucket viewers into a presence channel so
 * "active now" only considers people in roughly the same area.
 */

/** Geohash base-32 alphabet (note: excludes a, i, l, o). */
const BASE32 = "0123456789bcdefghjkmnpqrstuvwxyz";

/**
 * Encode a coordinate to a geohash of the given precision (default 4).
 *
 * Pure and deterministic — unit-testable without any test infrastructure:
 *   geohashEncode(51.5074, -0.1278) === "gcpv"   // London
 *   geohashEncode(-33.8688, 151.2093) === "r3gx" // Sydney
 */
export function geohashEncode(
  lat: number,
  lng: number,
  precision = 4,
): string {
  let idx = 0; // index into BASE32 for the current character
  let bit = 0; // bit position within the current 5-bit group
  let evenBit = true; // even bits encode longitude, odd bits latitude
  let hash = "";

  let latMin = -90;
  let latMax = 90;
  let lngMin = -180;
  let lngMax = 180;

  while (hash.length < precision) {
    if (evenBit) {
      // Bisect longitude.
      const lngMid = (lngMin + lngMax) / 2;
      if (lng >= lngMid) {
        idx = idx * 2 + 1;
        lngMin = lngMid;
      } else {
        idx = idx * 2;
        lngMax = lngMid;
      }
    } else {
      // Bisect latitude.
      const latMid = (latMin + latMax) / 2;
      if (lat >= latMid) {
        idx = idx * 2 + 1;
        latMin = latMid;
      } else {
        idx = idx * 2;
        latMax = latMid;
      }
    }
    evenBit = !evenBit;

    if (++bit === 5) {
      hash += BASE32[idx];
      bit = 0;
      idx = 0;
    }
  }

  return hash;
}
