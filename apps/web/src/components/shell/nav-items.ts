import { Bell, Compass, Home, MessageCircle, type LucideIcon } from "lucide-react";

export interface NavItem {
  /** Key under the `nav` message namespace. */
  labelKey: "home" | "discover" | "notifications" | "messages";
  href: string;
  icon: LucideIcon;
}

export const NAV_ITEMS: NavItem[] = [
  { labelKey: "home", href: "/home", icon: Home },
  { labelKey: "discover", href: "/discover", icon: Compass },
  { labelKey: "notifications", href: "/notifications", icon: Bell },
  { labelKey: "messages", href: "/messages", icon: MessageCircle },
];
