import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppDialog from '@/components/app-dialog';
import AppSelect from '@/components/app-select';
import InputError from '@/components/input-error';
import type { TenantRole } from '@/types/auth';
import type { TenantMemberRow } from './types';

const roleOptions = [
    { value: 'member', label: 'Member' },
    { value: 'tenant_admin', label: 'Tenant admin' },
];

type ChangeMemberRoleDialogProps = {
    member: TenantMemberRow;
    updateUrl: string;
    onOpenChange: (open: boolean) => void;
};

export default function ChangeMemberRoleDialog({
    member,
    updateUrl,
    onOpenChange,
}: ChangeMemberRoleDialogProps) {
    const form = useForm<{ role: TenantRole }>({ role: member.role });

    const onUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.put(updateUrl, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <AppDialog
            open
            title="Change member role"
            description={`Update the role for ${member.user.name}.`}
            submitLabel="Save changes"
            onOpenChange={(open) => {
                if (!open) {
                    form.reset();
                }

                onOpenChange(open);
            }}
            onSubmit={onUpdate}
            processing={form.processing}
        >
            <div className="space-y-1.5">
                <AppSelect
                    value={form.data.role}
                    options={roleOptions}
                    label="Role"
                    ariaLabel="Change member role"
                    triggerClassName="w-40"
                    onValueChange={(value) =>
                        form.setData('role', value as TenantRole)
                    }
                />
                <InputError message={form.errors.role} />
            </div>
        </AppDialog>
    );
}
