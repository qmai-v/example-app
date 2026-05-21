import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { update as settingsUpdate } from '@/actions/App/Http/Controllers/Tenant/SettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TenantStatus } from '@/types/auth';

type Props = {
    currentTenant: {
        id: string;
        name: string;
        slug: string;
        status: TenantStatus;
    };
    canEditSlug: boolean;
};

export default function TenantSettings({ currentTenant, canEditSlug }: Props) {
    const form = useForm<{ name: string }>({ name: currentTenant.name });

    useEffect(() => {
        form.setData('name', currentTenant.name);
        form.setDefaults('name', currentTenant.name);
        form.clearErrors('name');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentTenant.id, currentTenant.name]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(settingsUpdate.url(), {
            fresh: true,
            preserveScroll: true,
            preserveState: false,
            replace: true,
        });
    };

    return (
        <div className="mx-auto w-full max-w-3xl space-y-8 py-8">
            <Head title="Tenant settings" />
            <Heading
                title="Tenant settings"
                description="Update your tenant's display name. The slug and status are managed by system administrators."
            />

            <form onSubmit={submit} className="space-y-6">
                <div className="space-y-1.5">
                    <Label htmlFor="tenant-name">Tenant name</Label>
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
                    <Label htmlFor="tenant-slug">Slug</Label>
                    <Input
                        id="tenant-slug"
                        value={currentTenant.slug}
                        disabled={!canEditSlug}
                        readOnly={!canEditSlug}
                    />
                </div>

                <div className="space-y-1.5">
                    <Label>Status</Label>
                    <p className="text-sm text-muted-foreground capitalize">
                        {currentTenant.status}
                    </p>
                </div>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        Save changes
                    </Button>
                </div>
            </form>
        </div>
    );
}
