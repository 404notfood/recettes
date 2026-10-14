import { Download, ChevronDown, FileText, ShoppingBasket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export default function MenuPdfExport({
    planId,
    disabled,
}: {
    planId: number;
    disabled: boolean;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className="menu-pill"
                    disabled={disabled}
                >
                    <Download size={16} /> Télécharger le PDF{' '}
                    <ChevronDown size={14} />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {[
                    ['full', 'Menus et courses'],
                    ['menus', 'Les menus'],
                    ['shopping', 'La liste de courses'],
                ].map(([document, label]) => (
                    <DropdownMenuItem key={document} asChild>
                        <a href={`/menus/${planId}/pdf?document=${document}`}>
                            {document === 'shopping' ? (
                                <ShoppingBasket />
                            ) : (
                                <FileText />
                            )}
                            {label}
                        </a>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
