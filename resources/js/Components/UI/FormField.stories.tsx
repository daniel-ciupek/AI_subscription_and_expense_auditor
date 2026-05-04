import type { Meta, StoryObj } from '@storybook/react-vite';
import { FormField } from './FormField';
import { Input } from './Input';

const meta = {
    title: 'UI/FormField',
    component: FormField,
    tags: ['autodocs'],
    decorators: [
        (Story) => (
            <div className="w-80">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof FormField>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
    args: {
        label: 'Subscription name',
        children: (id) => <Input id={id} placeholder="NETFLIX SUBSCRIPTION" />,
    },
};

export const Required: Story = {
    args: {
        label: 'Amount',
        required: true,
        children: (id) => (
            <Input
                id={id}
                type="number"
                step="0.01"
                placeholder="49.99"
                className="font-mono tabular-nums"
            />
        ),
    },
};

export const WithHelper: Story = {
    args: {
        label: 'Currency',
        required: true,
        helperText: '3-letter ISO code (PLN, EUR, USD…)',
        children: (id) => (
            <Input id={id} maxLength={3} className="font-mono uppercase" />
        ),
    },
};

export const WithError: Story = {
    args: {
        label: 'Currency',
        required: true,
        error: 'The currency field format is invalid.',
        children: (id) => (
            <Input
                id={id}
                defaultValue="EURO"
                error
                className="font-mono uppercase"
            />
        ),
    },
};
