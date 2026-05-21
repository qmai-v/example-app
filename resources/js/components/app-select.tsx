import type { ComponentProps, ReactNode } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type AppSelectOption = {
    value: string;
    label: ReactNode;
    disabled?: boolean;
};

type AppSelectProps = {
    value: string;
    options: AppSelectOption[];
    onValueChange: (value: string) => void;
    ariaLabel?: string;
    className?: string;
    contentAlign?: ComponentProps<typeof SelectContent>['align'];
    contentClassName?: string;
    contentPosition?: ComponentProps<typeof SelectContent>['position'];
    label?: ReactNode;
    placeholder?: string;
    triggerClassName?: string;
    triggerSize?: ComponentProps<typeof SelectTrigger>['size'];
};

export default function AppSelect({
    value,
    options,
    onValueChange,
    ariaLabel,
    className,
    contentAlign = 'start',
    contentClassName,
    contentPosition = 'item-aligned',
    label,
    placeholder,
    triggerClassName,
    triggerSize = 'sm',
}: AppSelectProps) {
    return (
        <div className={cn('inline-flex items-center gap-2', className)}>
            {label && (
                <span className="text-sm leading-none text-muted-foreground">
                    {label}
                </span>
            )}

            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger
                    size={triggerSize}
                    className={cn(
                        'h-8 min-w-20 cursor-pointer justify-between',
                        triggerClassName,
                    )}
                    aria-label={ariaLabel}
                >
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent
                    align={contentAlign}
                    className={contentClassName}
                    position={contentPosition}
                >
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
