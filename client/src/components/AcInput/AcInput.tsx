import React, {FocusEventHandler, FormEvent, useEffect, useState} from 'react';
import styles from './AcInput.module.scss';
import {cx} from '../../ui';
import {SearchResponse, Suggestion} from '../../types';
import Suggestions from './Suggestions';

interface AcInputProps {
  name: string;
  isActive: boolean;
  placeholder: string;
  typingResponse: SearchResponse | null;
  relatedResponse: SearchResponse | null;
  selected: Suggestion | null;
  onFocus: FocusEventHandler;
  onTyping: { (value: string): void };
  onSelect: { (suggestion: Suggestion): void };
}

export default function AcInput(props: AcInputProps) {
  const [value, setValue] = useState('');

  const {name, isActive, typingResponse, relatedResponse, selected, onTyping, onSelect} = props;

  function handleChange(e: FormEvent) {
    const value = ((e.target as HTMLInputElement).value || '').trim();
    setValue(value);
    onTyping(value);
  }

  useEffect(() => {
    if (selected) {
      setValue(selected.value);
    }
  }, [name, selected]);

  // Related values use hits, not aggregations. Filter out empty values.
  const relatedWithValue = relatedResponse && {
    ...relatedResponse,
    hits: relatedResponse.hits.map(s => ({...s, value: s.values![name]})).filter(_ => !!_.value)
  };
  const hasRelatedItems = relatedWithValue && relatedWithValue.hits.length > 0;

  // Display if current input is active and there's typing response or if there's a related response.
  const showSuggestions = Boolean(hasRelatedItems || (isActive && typingResponse));
  const className = cx(styles.AcInputContainer, showSuggestions && styles.opened);

  return (
    <div className={className}>
      <div className={styles.Input}>
        <input
          name={name}
          type="text"
          value={value}
          placeholder={props.placeholder}
          onChange={handleChange}
          onFocus={props.onFocus}
        />
      </div>

      <Suggestions
        data={relatedWithValue || typingResponse}
        selected={selected}
        showAggregations={!relatedResponse}
        onSelect={onSelect}
        style={{display: showSuggestions ? 'block' : 'none'}}
      />
    </div>
  );
}
