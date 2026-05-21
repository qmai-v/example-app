import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { store as adminTenantsStore } from '@/actions/App/Http/Controllers/Admin/TenantController';
import AppDialog from '@/components/app-dialog';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CreateTenantDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function CreateTenantDialog({
    open,
    onOpenChange,
}: CreateTenantDialogProps) {
    const form = useForm<{
        name: string;
        slug: string;
        initial_tenant_admin_user_id: string;
    }>({
        name: '',
        slug: '',
        initial_tenant_admin_user_id: '',
    });

    const onCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.post(adminTenantsStore.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <AppDialog
            open={open}
            title="Create tenant"
            description="Provision a new tenant and assign its initial tenant admin by user id."
            submitLabel="Create tenant"
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    form.reset();
                }

                onOpenChange(nextOpen);
            }}
            onSubmit={onCreate}
            processing={form.processing}
        >
            <div className="space-y-4">
                <div className="space-y-1.5">
                    <Label htmlFor="tenant-name">Name</Label>
                    <Input
                        id="tenant-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="tenant-slug">Slug (optional)</Label>
                    <Input
                        id="tenant-slug"
                        value={form.data.slug}
                        onChange={(event) =>
                            form.setData('slug', event.target.value)
                        }
                        placeholder="auto-from-name"
                    />
                    <InputError message={form.errors.slug} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="tenant-admin-id">
                        Initial tenant admin (user id)
                    </Label>
                    <Input
                        id="tenant-admin-id"
                        value={form.data.initial_tenant_admin_user_id}
                        onChange={(event) =>
                            form.setData(
                                'initial_tenant_admin_user_id',
                                event.target.value,
                            )
                        }
                    />
                    <InputError
                        message={form.errors.initial_tenant_admin_user_id}
                    />
                </div>
            </div>
        </AppDialog>
    );
}
