export interface User {
  id: number;
  name: string;
  email: string;
  locale: string | null;
  timezone: string | null;
  settings: Record<string, unknown> | null;
}

export type ProfileKind = "personal" | "business";
export type Relationship = "none" | "following" | "pending" | "self";

export interface Profile {
  ulid: string;
  kind: ProfileKind;
  handle: string;
  name: string;
  bio: string | null;
  avatar_url: string | null;
  cover_url: string | null;
  category: string | null;
  website: string | null;
  location: string | null;
  is_private: boolean;
  is_verified: boolean;
  followers_count: number;
  following_count: number;
  posts_count: number;
  badges: string[];
  relationship: Relationship;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    next_cursor: string | null;
  };
}

export interface MeResponse {
  data: {
    user: User;
    profiles: Profile[];
    active_profile: Profile;
  };
}

export type ApiValidationErrors = Record<string, string[]>;
