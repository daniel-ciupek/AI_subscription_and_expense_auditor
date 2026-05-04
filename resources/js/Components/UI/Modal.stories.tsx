import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { AlertCircle, Trash2 } from 'lucide-react';
import { Button } from './Button';
import { FormField } from './FormField';
import { Input } from './Input';
import { Modal } from './Modal';

const meta: Meta<typeof Modal> = {
    title: 'UI/Modal',
    component: Modal,
    tags: ['autodocs'],
    parameters: { layout: 'fullscreen' },
};

export default meta;
type Story = StoryObj<typeof Modal>;

export const Confirm: Story = {
    render: () => {
        const [open, setOpen] = useState(true);

        return (
            <div className="min-h-screen flex items-center justify-center">
                <Button onClick={() => setOpen(true)}>Open modal</Button>
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title="Delete subscription?"
                    maxWidth="sm"
                >
                    <div className="flex items-start gap-3 mb-5">
                        <div className="rounded-xl bg-state-danger/10 p-2 ring-1 ring-state-danger/30 shrink-0">
                            <AlertCircle
                                className="h-5 w-5 text-state-danger"
                                aria-hidden="true"
                            />
                        </div>
                        <p className="text-sm text-text-secondary">
                            This removes{' '}
                            <span className="text-text-primary font-medium">
                                NETFLIX SUBSCRIPTION
                            </span>{' '}
                            from your subscription list. The matching transactions
                            stay intact.
                        </p>
                    </div>
                    <div className="flex items-center justify-end gap-2">
                        <Button variant="ghost" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="danger">
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                            Delete
                        </Button>
                    </div>
                </Modal>
            </div>
        );
    },
};

export const Form: Story = {
    render: () => {
        const [open, setOpen] = useState(true);

        return (
            <div className="min-h-screen flex items-center justify-center">
                <Button onClick={() => setOpen(true)}>Open form modal</Button>
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title="Edit subscription"
                    description="Adjust the merchant, amount, or billing cadence."
                    maxWidth="lg"
                >
                    <form className="flex flex-col gap-4">
                        <FormField label="Name" required>
                            {(id) => (
                                <Input id={id} defaultValue="NETFLIX SUBSCRIPTION" />
                            )}
                        </FormField>
                        <div className="grid grid-cols-2 gap-4">
                            <FormField label="Amount" required>
                                {(id) => (
                                    <Input
                                        id={id}
                                        type="number"
                                        step="0.01"
                                        defaultValue="49.99"
                                        className="font-mono tabular-nums"
                                    />
                                )}
                            </FormField>
                            <FormField label="Currency" required>
                                {(id) => (
                                    <Input
                                        id={id}
                                        defaultValue="PLN"
                                        maxLength={3}
                                        className="font-mono uppercase"
                                    />
                                )}
                            </FormField>
                        </div>
                        <div className="flex items-center justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" variant="primary">
                                Save changes
                            </Button>
                        </div>
                    </form>
                </Modal>
            </div>
        );
    },
};

export const NoCloseButton: Story = {
    render: () => {
        const [open, setOpen] = useState(true);

        return (
            <div className="min-h-screen flex items-center justify-center">
                <Button onClick={() => setOpen(true)}>Open</Button>
                <Modal
                    open={open}
                    onClose={() => setOpen(false)}
                    title="Heads up"
                    showClose={false}
                    maxWidth="sm"
                >
                    <p className="text-sm text-text-secondary mb-4">
                        This dialog has no close button — only the Confirm action
                        dismisses it.
                    </p>
                    <div className="flex justify-end">
                        <Button onClick={() => setOpen(false)}>Got it</Button>
                    </div>
                </Modal>
            </div>
        );
    },
};
