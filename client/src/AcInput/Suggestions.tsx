import { Suggestion } from '../types';
import styles from './AcInput.module.scss';
import React from 'react';

interface SuggestionsProps {
    suggestions: Suggestion[];
    selected: Suggestion | null;
    onSelect: { (suggestion: Suggestion): void };
    style: React.CSSProperties | undefined;
}

export default function Suggestions({ suggestions, selected, onSelect, style }: SuggestionsProps) {
    const items =
        suggestions.length > 0 ? (
            suggestions.map((item: Suggestion) => (
                <li key={item.id} className={selected && selected.id === item.id ? styles.active : ''}>
                    <button className={styles.itemButton} onClick={() => onSelect(item)}>
                        {item.value}
                    </button>
                </li>
            ))
        ) : (
            <li>No results.</li>
        );

    return (
        <ul className={styles.Suggestions} style={style}>
            {items}
        </ul>
    );
}
