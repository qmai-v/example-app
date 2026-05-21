import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UserFieldsProps = {
    idPrefix: string;
    name: string;
    email: string;
    onNameChange: (value: string) => void;
    onEmailChange: (value: string) => void;
    errors: Partial<Record<'name' | 'email', string>>;
};

export default function UserFields({
    idPrefix,
    name,
    email,
    onNameChange,
    onEmailChange,
    errors,
}: UserFieldsProps) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-user-name`}>Name</Label>
                <Input
                    id={`${idPrefix}-user-name`}
                    value={name}
                    onChange={(event) => onNameChange(event.target.value)}
                    autoComplete="name"
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-user-email`}>Email</Label>
                <Input
                    id={`${idPrefix}-user-email`}
                    type="email"
                    value={email}
                    onChange={(event) => onEmailChange(event.target.value)}
                    autoComplete="username"
                    required
                />
                <InputError message={errors.email} />
            </div>
        </div>
    );
}
