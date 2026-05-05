import type { Meta, StoryObj } from '@storybook/react-vite';
import { SkeletonCard } from './SkeletonCard';

const meta = {
    title: 'UI/SkeletonCard',
    component: SkeletonCard,
    tags: ['autodocs'],
    decorators: [
        (Story) => (
            <div className="w-80">
                <Story />
            </div>
        ),
    ],
    argTypes: {
        lines: { control: { type: 'range', min: 1, max: 6, step: 1 } },
    },
} satisfies Meta<typeof SkeletonCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const SingleLine: Story = {
    args: { lines: 1 },
};

export const ManyLines: Story = {
    args: { lines: 5 },
};

export const Stacked: Story = {
    render: () => (
        <div className="flex flex-col gap-3 w-80">
            <SkeletonCard lines={2} />
            <SkeletonCard lines={3} />
            <SkeletonCard lines={4} />
        </div>
    ),
};
