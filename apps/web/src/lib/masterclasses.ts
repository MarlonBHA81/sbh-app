import * as api from "@/lib/api/client";

/** A masterclass as returned by the facilitator authoring endpoints. */
export interface MyMasterclass {
  ulid: string;
  title: string;
  description: string;
  facilitator_name: string | null;
  starts_at: string;
  ends_at: string;
  capacity: number | null;
  participants_count: number;
  is_published: boolean;
  status: "upcoming" | "active" | "ended";
}

export interface MasterclassInput {
  title: string;
  description: string;
  starts_at: string;
  ends_at: string;
  capacity?: number | null;
  is_published?: boolean;
}

export function listMyMasterclasses(): Promise<{ data: MyMasterclass[] }> {
  return api.get<{ data: MyMasterclass[] }>("/api/v1/me/masterclasses");
}

export function createMasterclass(
  input: MasterclassInput,
): Promise<{ data: MyMasterclass }> {
  return api.post<{ data: MyMasterclass }>("/api/v1/me/masterclasses", input);
}

export function updateMasterclass(
  ulid: string,
  input: Partial<MasterclassInput>,
): Promise<{ data: MyMasterclass }> {
  return api.patch<{ data: MyMasterclass }>(
    `/api/v1/me/masterclasses/${ulid}`,
    input,
  );
}

export function deleteMasterclass(ulid: string): Promise<void> {
  return api.del(`/api/v1/me/masterclasses/${ulid}`);
}
