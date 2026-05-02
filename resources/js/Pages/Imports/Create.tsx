import { ChangeEvent, DragEvent, FormEventHandler, useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { Upload, FileText, X } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { FormField } from '@/Components/UI/FormField';
import { cn } from '@/lib/cn';

interface BankOption {
    value: string;
    label: string;
}

interface FormData {
    file: File | null;
    bank: string;
}

export default function ImportsCreate({ banks }: { banks: BankOption[] }) {
    const [dragOver, setDragOver] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const reduce = useReducedMotion();

    const { data, setData, post, processing, errors, reset, progress } =
        useForm<FormData>({
            file: null,
            bank: '',
        });

    const handleFile = (file: File | null) => {
        setData('file', file);
    };

    const onChange = (e: ChangeEvent<HTMLInputElement>) => {
        handleFile(e.target.files?.[0] ?? null);
    };

    const onDrop = (e: DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        setDragOver(false);
        const file = e.dataTransfer.files?.[0];
        if (file) handleFile(file);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('imports.store'), {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-semibold text-text-primary">
                        Upload bank CSV
                    </h1>
                    <p className="text-sm text-text-secondary mt-1">
                        Drag a statement file or pick a bank if auto-detection fails.
                    </p>
                </div>
            }
        >
            <Head title="Import CSV" />

            <form onSubmit={submit} className="max-w-2xl flex flex-col gap-6">
                <Card>
                    <motion.div
                        onDragEnter={(e) => {
                            e.preventDefault();
                            setDragOver(true);
                        }}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragOver(true);
                        }}
                        onDragLeave={() => setDragOver(false)}
                        onDrop={onDrop}
                        onClick={() => inputRef.current?.click()}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                inputRef.current?.click();
                            }
                        }}
                        animate={
                            reduce ? undefined : dragOver ? { scale: 1.01 } : { scale: 1 }
                        }
                        transition={{ duration: reduce ? 0 : 0.15 }}
                        className={cn(
                            'cursor-pointer rounded-2xl border-2 border-dashed p-10 text-center transition-colors duration-200',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface',
                            dragOver
                                ? 'border-accent-neon bg-accent-neon/5'
                                : 'border-white/15 hover:border-white/30 hover:bg-white/[0.03]',
                        )}
                        role="button"
                        tabIndex={0}
                        aria-label="Upload CSV file"
                    >
                        <input
                            ref={inputRef}
                            type="file"
                            accept=".csv,.txt,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            className="sr-only"
                            onChange={onChange}
                        />

                        {data.file ? (
                            <div className="flex items-center justify-center gap-3">
                                <FileText
                                    className="h-8 w-8 text-accent-neon"
                                    aria-hidden="true"
                                />
                                <div className="text-left">
                                    <p className="text-sm text-text-primary font-medium truncate max-w-[300px]">
                                        {data.file.name}
                                    </p>
                                    <p className="text-xs text-text-secondary font-mono">
                                        {(data.file.size / 1024).toFixed(1)} kB
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        handleFile(null);
                                    }}
                                    className="ml-2 text-text-secondary hover:text-state-danger transition-colors rounded-md p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface"
                                    aria-label="Remove file"
                                >
                                    <X className="h-5 w-5" />
                                </button>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3">
                                <div className="rounded-full bg-accent-primary/10 p-4 ring-1 ring-accent-primary/30">
                                    <Upload
                                        className="h-7 w-7 text-accent-neon"
                                        aria-hidden="true"
                                    />
                                </div>
                                <p className="text-text-primary font-medium">
                                    Drop file here or click to choose
                                </p>
                                <p className="text-xs text-text-secondary">
                                    CSV, XLS or XLSX — mBank, PKO BP, ING, Santander, BGŻ BNP Paribas — max 10 MB
                                </p>
                            </div>
                        )}
                    </motion.div>

                    {errors.file && (
                        <p className="mt-3 text-xs text-state-danger" role="alert">
                            {errors.file}
                        </p>
                    )}
                </Card>

                <Card>
                    <FormField
                        label="Bank (optional — auto-detected from headers)"
                        error={errors.bank}
                    >
                        {(id) => (
                            <select
                                id={id}
                                value={data.bank}
                                onChange={(e) => setData('bank', e.target.value)}
                                className="h-10 w-full rounded-2xl px-4 text-sm bg-bg-surface border border-white/10 text-text-primary focus:border-accent-neon/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base"
                            >
                                <option value="">Auto-detect</option>
                                {banks.map((b) => (
                                    <option key={b.value} value={b.value}>
                                        {b.label}
                                    </option>
                                ))}
                            </select>
                        )}
                    </FormField>
                </Card>

                {progress && (
                    <div className="text-xs text-text-secondary font-mono">
                        Uploading… {progress.percentage}%
                    </div>
                )}

                <div className="flex justify-between items-center">
                    <Link
                        href={route('imports.index')}
                        className="text-sm text-text-secondary hover:text-text-primary transition-colors rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base"
                    >
                        ← Back to imports
                    </Link>
                    <Button
                        type="submit"
                        disabled={!data.file}
                        loading={processing}
                    >
                        Upload &amp; process
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}
