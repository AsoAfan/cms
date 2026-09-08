import { Link } from '@inertiajs/react';

import { MoneyDisplay } from '@/components/money-display';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import banks from '@/routes/settings/banks';
import type { BankBalances as BankBalancesProps } from '@/types/reports';

/**
 * What each account holds today.
 *
 * A position, not period arithmetic — the same standing as what customers owe,
 * which is why it sits beside the tiles rather than among them and carries no
 * comparison against the stretch before. `BankBalanceQuery` derives every
 * figure from the documents that moved through the account, so a balance here
 * and the outcome above it always describe the same set of them.
 */
export function BankBalances({ accounts, total }: BankBalancesProps) {
    if (accounts.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Bank balances</CardTitle>
                <CardDescription>
                    What each account holds now, whatever period is showing
                    above.
                </CardDescription>

                <CardAction className="text-right">
                    <p className="text-xs text-muted-foreground">Together</p>
                    <MoneyDisplay
                        amount={total}
                        colored
                        className="text-lg font-medium"
                    />
                </CardAction>
            </CardHeader>
            <CardContent>
                <dl className="divide-y">
                    {accounts.map((account) => (
                        <div
                            key={account.id}
                            className="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0"
                        >
                            <dt className="text-sm">
                                <Link
                                    href={banks.index.url()}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {account.name}
                                </Link>
                            </dt>
                            <dd>
                                <MoneyDisplay
                                    amount={account.balance}
                                    colored
                                />
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}
