import React, { FocusEventHandler, FormEvent, useEffect, useState } from 'react';
import styles from './AcInput.module.scss';
import { cx } from '../ui';
import { RelatedSuggestion, Suggestion } from '../types';
import Suggestions from './Suggestions';
import { fetchSuggestions } from '../data';

interface AcInputProps {
    name: string;
    active: boolean;
    placeholder: string;
    related: RelatedSuggestion[] | null;
    selected: Suggestion | null;
    onFocus: FocusEventHandler;
    onSelect: { (suggestion: Suggestion): void };
}

export default function AcInput({ name, active, placeholder, related, selected, onFocus, onSelect }: AcInputProps) {
    const [value, setValue] = useState('');
    const [suggestions, setSuggestions] = useState<Suggestion[] | null>(null);

    function handleChange(e: FormEvent) {
        const value = (e.target as HTMLInputElement).value || '';
        setValue(value);

        if (related) {
            // todo: search across related. maybe do a request to the server considering the limited amount of related we can load
            return;
        }
        if (value) {
            fetchSuggestions(name, value).then(data => setSuggestions(data));
        } else {
            setSuggestions(null);
        }
    }

    useEffect(() => {
        if (selected) {
            setValue(selected.value);
        }
    }, [selected]);

    const showSuggestions = Boolean((active && suggestions) || related);
    const className = cx(styles.AcInputContainer, showSuggestions && styles.opened);

    return (
        <div className={className}>
            <div className={styles.Input}>
                <input
                    name={name}
                    type="text"
                    value={value}
                    placeholder={placeholder}
                    onChange={handleChange}
                    onFocus={onFocus}
                />
            </div>

            <Suggestions
                suggestions={suggestions || related || []}
                selected={selected}
                onSelect={onSelect}
                style={{ display: showSuggestions ? 'block' : 'none' }}
            />
        </div>
    );
}
