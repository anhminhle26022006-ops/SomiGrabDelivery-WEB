export const APP_CONFIG = {
  name: "SOMI",
  matchingOfferSeconds: 20,
  initialMatchingRadiusKm: 2,
  maxMatchingRadiusKm: 10,
  defaultDistanceKm: 4,
  defaultWeightKg: 2,
} as const;

export const HERO_VIDEO_URL =
  import.meta.env.VITE_HERO_VIDEO_URL ||
  "https://videos.pexels.com/video-files/7835290/7835290-uhd_3840_2160_25fps.mp4";
