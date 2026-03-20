import * as React from "react";
import { cn } from "./utils";

interface SegmentedControlProps<T extends string> {
  value: T;
  onChange: (value: T) => void;
  options: { value: T; label: string }[];
  className?: string;
}

export function SegmentedControl<T extends string>({
  value,
  onChange,
  options,
  className,
}: SegmentedControlProps<T>) {
  return (
    <div
      className={cn(
        "flex items-center border border-border rounded-lg overflow-hidden",
        className
      )}
    >
      {options.map((option, index) => (
        <button
          key={option.value}
          type="button"
          onClick={() => onChange(option.value)}
          className={cn(
            "px-4 py-2 text-sm transition-colors",
            index > 0 && "border-l border-border",
            value === option.value
              ? "bg-primary text-primary-foreground"
              : "bg-card hover:bg-muted"
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  );
}