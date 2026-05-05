import type { Meta, StoryObj } from '@storybook/react-vite';
import { Upload, Repeat, BellOff } from 'lucide-react';
import { Button } from './Button';
import { EmptyState } from './EmptyState';

const meta = {
    title: 'UI/EmptyState',
    component: EmptyState,
    tags: ['autodocs'],
    parameters: { layout: 'fullscreen' },
} satisfies Meta<typeof EmptyState>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NoImports: Story = {
    args: {
        icon: Upload,
        title: 'No imports yet',
        description:
            'Upload your first bank CSV to start tracking transactions and subscriptions.',
        action: <Button>Upload CSV</Button>,
    },
};

export const NoSubscriptions: Story = {
    args: {
        icon: Repeat,
        title: 'No subscriptions detected',
        description:
            'Once you have a few months of transactions imported, hit "Run detection" to surface recurring charges.',
        action: <Button>Run detection</Button>,
    },
};

export const NothingInInbox: Story = {
    args: {
        icon: BellOff,
        title: 'Nothing in your inbox yet',
        description:
            "We'll let you know a few days before each detected subscription charges your account.",
    },
};

export const TitleOnly: Story = {
    args: {
        icon: Upload,
        title: 'Nothing here',
    },
};
