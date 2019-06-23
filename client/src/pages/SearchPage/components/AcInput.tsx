import React, { useEffect, useRef, useState } from 'react';
import styles from './AcInput.module.scss';
import { cx } from '../../../ui';
import { SearchResponse, Suggestion } from '../../../types';
import Suggestions from './Suggestions';
import { formatTotal } from '../../../utils';

interface AcInputProps {
  name: string;
  value: string;
  placeholder: string;
  response: SearchResponse | null;
  selected: Suggestion | null;
  onTyping: { (value: string): void };
  onSelect: { (suggestion: Suggestion): void };
}

/**
 * A field input with autocomplete support.
 */
export default function AcInput(props: AcInputProps) {
  const [isActive, setActive] = useState<boolean>(false);
  const ref = useRef<HTMLDivElement>(null);

  const { name, value, response, selected, onTyping, onSelect } = props;

  // Activate the input when related or typing suggestions were updated.
  useEffect(() => {
    if (response) {
      setActive(true);
    }
  }, [response]);

  // Deactivate on click outside.
  useEffect(() => {
    const close = (e: any) => (ref.current && !ref.current.contains(e.target) ? setActive(false) : null);
    document.addEventListener('click', close);
    return () => document.removeEventListener('click', close);
  }, []);

  // Display if current input is active and there's typing response or if there's a related response.
  const showSuggestions = Boolean(isActive && response);
  const className = cx(styles.AcInputContainer, showSuggestions && styles.opened);

  return (
    <div className={className} ref={ref}>
      {response && response.suggestions.length > 0 && (
        <div className={styles.meta}>
          total <b>{formatTotal(response.total)}</b> took <b>{response.took}ms</b>
        </div>
      )}

      <div className={styles.Input}>
        <input
          name={name}
          type="text"
          value={value}
          placeholder={props.placeholder}
          onChange={e => onTyping((e.target as HTMLInputElement).value || '')}
          onFocus={() => setActive(true)}
        />
      </div>

      <Suggestions
        data={response ? response.suggestions : []}
        selected={selected}
        onSelect={s => {
          setActive(false);
          onSelect(s);
        }}
        style={{ display: showSuggestions ? 'block' : 'none' }}
      />
    </div>
  );
}
