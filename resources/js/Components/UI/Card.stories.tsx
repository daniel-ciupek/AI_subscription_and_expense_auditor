import type { Meta, StoryObj } from '@storybook/react-vite';
import { Card } from './Card';

const meta = {
    title: 'UI/Card',
    component: Card,
    tags: ['autodocs'],
    argTypes: {
        hoverable: { control: 'boolean' },
        elevated: { control: 'boolean' },
    },
} satisfies Meta<typeof Card>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
    args: {
        children: (
            <>
                <h3 className="text-base font-semibold text-text-primary">
                    NETFLIX SUBSCRIPTION
                </h3>
                <p className="text-xs text-text-secondary mt-1 font-mono">
                    Monthly · 49.99 PLN
                </p>
            </>
        ),
    },
};

export const Elevated: Story = {
    args: {
        elevated: true,
        children: (
            <>
                <p className="text-xs text-text-secondary">Monthly cost</p>
                <p className="mt-2 text-2xl font-mono tabular-nums text-text-primary">
                    49.99
                </p>
                <p className="text-[10px] text-text-secondary mt-1 uppercase tracking-wider">
                    PLN
                </p>
            </>
        ),
    },
};

export const Hoverable: Story = {
    args: {
        hoverable: true,
        children: (
            <>
                <h3 className="text-base font-semibold text-text-primary">
                    Hover me
                </h3>
                <p className="text-xs text-text-secondary mt-1">
                    The whole card lifts and brightens.
                </p>
            </>
        ),
    },
};
