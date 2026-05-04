import type { Meta, StoryObj } from '@storybook/react-vite';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from './Button';

const meta = {
    title: 'UI/Button',
    component: Button,
    tags: ['autodocs'],
    args: {
        children: 'Click me',
    },
    argTypes: {
        variant: {
            control: { type: 'inline-radio' },
            options: ['primary', 'secondary', 'ghost', 'danger'],
        },
        size: {
            control: { type: 'inline-radio' },
            options: ['sm', 'md', 'lg'],
        },
        loading: { control: 'boolean' },
        disabled: { control: 'boolean' },
    },
} satisfies Meta<typeof Button>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Primary: Story = {
    args: { variant: 'primary' },
};

export const Secondary: Story = {
    args: { variant: 'secondary' },
};

export const Ghost: Story = {
    args: { variant: 'ghost' },
};

export const Danger: Story = {
    args: { variant: 'danger', children: 'Delete account' },
};

export const Loading: Story = {
    args: { loading: true, children: 'Saving…' },
};

export const Disabled: Story = {
    args: { disabled: true },
};

export const WithIcon: Story = {
    args: {
        children: (
            <>
                <Plus className="h-4 w-4" aria-hidden="true" />
                New import
            </>
        ),
    },
};

export const DangerWithIcon: Story = {
    args: {
        variant: 'danger',
        children: (
            <>
                <Trash2 className="h-4 w-4" aria-hidden="true" />
                Delete
            </>
        ),
    },
};

export const Sizes: Story = {
    render: () => (
        <div className="flex items-center gap-3">
            <Button size="sm">Small</Button>
            <Button size="md">Medium</Button>
            <Button size="lg">Large</Button>
        </div>
    ),
};
