import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import AppDialog from '@/components/app-dialog';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import type { UserRow } from '@/types';
import type {
    CreateUserForm,
    UpdateUserForm,
    UserStatusFilter,
} from '../types';
import UserFields from './user-fields';

type UserFormDialogBaseProps = {
    open: boolean;
    currentPage: number;
    perPage: number;
    search: string;
    status: UserStatusFilter;
    onOpenChange: (open: boolean) => void;
};

type UserFormDialogProps = UserFormDialogBaseProps & {
    user?: UserRow | null;
};

export default function UserFormDialog(props: UserFormDialogProps) {
    const { open, currentPage, perPage, search, status, onOpenChange, user } =
        props;

    const createForm = useForm<CreateUserForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        search,
        status,
        page: currentPage,
        per_page: perPage,
    });

    const updateForm = useForm<UpdateUserForm>({
        name: user ? user.name : '',
        email: user ? user.email : '',
        search,
        status,
        page: currentPage,
        per_page: perPage,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        if (user) {
            updateForm.clearErrors();
            updateForm.setData({
                name: user.name,
                email: user.email,
                search,
                status,
                page: currentPage,
                per_page: perPage,
            });

            return;
        }

        createForm.clearErrors();
        createForm.setData({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            search,
            status,
            page: currentPage,
            per_page: perPage,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentPage, open, perPage, search, status, user]);

    const submitCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        createForm.post(UserController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                onOpenChange(false);
            },
        });
    };

    const submitUpdate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!user) {
            return;
        }

        updateForm.put(UserController.update.url(user.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const isCreate = !user;
    const idPrefix = isCreate ? 'create' : 'edit';
    const fieldData = isCreate ? createForm.data : updateForm.data;
    const fieldErrors = isCreate ? createForm.errors : updateForm.errors;

    return (
        <AppDialog
            open={open}
            title={isCreate ? 'Add user' : 'Edit user'}
            description={
                isCreate
                    ? 'Create a user account without leaving this page.'
                    : "Update the selected user's profile details."
            }
            submitLabel={isCreate ? 'Create user' : 'Save changes'}
            processing={
                isCreate ? createForm.processing : updateForm.processing
            }
            contentClassName={isCreate ? 'sm:max-w-xl' : undefined}
            onOpenChange={onOpenChange}
            onSubmit={isCreate ? submitCreate : submitUpdate}
            closeOnInteractOutside={isCreate ? false : undefined}
        >
            <UserFields
                idPrefix={idPrefix}
                name={fieldData.name}
                email={fieldData.email}
                onNameChange={(name) => {
                    if (isCreate) {
                        createForm.setData('name', name);

                        return;
                    }

                    updateForm.setData('name', name);
                }}
                onEmailChange={(email) => {
                    if (isCreate) {
                        createForm.setData('email', email);

                        return;
                    }

                    updateForm.setData('email', email);
                }}
                errors={fieldErrors}
            />

            {isCreate && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="create-password">Password</Label>
                        <PasswordInput
                            id="create-password"
                            value={createForm.data.password}
                            onChange={(event) =>
                                createForm.setData(
                                    'password',
                                    event.target.value,
                                )
                            }
                            autoComplete="new-password"
                        />
                        <InputError message={createForm.errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-password-confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="create-password-confirmation"
                            value={createForm.data.password_confirmation}
                            onChange={(event) =>
                                createForm.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            autoComplete="new-password"
                        />
                        <InputError
                            message={createForm.errors.password_confirmation}
                        />
                    </div>
                </div>
            )}
        </AppDialog>
    );
}
