import type { Meta, StoryObj } from '@storybook/react-vite';
import { Input } from './Input';

const meta = {
    title: 'UI/Input',
    component: Input,
    tags: ['autodocs'],
    args: {
        placeholder: 'Type something…',
    },
    argTypes: {
        type: {
            control: { type: 'select' },
            options: ['text', 'number', 'date', 'email', 'password'],
        },
        error: { control: 'boolean' },
        disabled: { control: 'boolean' },
    },
    decorators: [
        (Story) => (
            <div className="w-72">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof Input>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithValue: Story = {
    args: { defaultValue: 'NETFLIX SUBSCRIPTION' },
};

export const Error: Story = {
    args: { error: true, defaultValue: 'too-short' },
};

export const Disabled: Story = {
    args: { disabled: true, defaultValue: 'Cannot edit' },
};

export const Number: Story = {
    args: {
        type: 'number',
        defaultValue: '49.99',
        className: 'font-mono tabular-nums',
    },
};

export const Date: Story = {
    args: { type: 'date', defaultValue: '2026-05-04' },
};
