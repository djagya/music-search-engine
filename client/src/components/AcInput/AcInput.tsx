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

/**
 * A field input with autocomplete support.
 */
export default function AcInput(props: AcInputProps) {
  const [value, setValue] = useState('');
  const {name, isActive, typingResponse, relatedResponse, selected, onTyping, onSelect} = props;

  // Related values use hits, not aggregations. Filter out empty values.
  const relatedWithValue = relatedResponse && {
    ...relatedResponse,
    suggestions: relatedResponse.suggestions.filter(_ => !!_.value)
  };
  const hasRelatedItems = relatedWithValue && relatedWithValue.suggestions.length > 0;

  // Display if current input is active and there's typing response or if there's a related response.
  const showSuggestions = Boolean(hasRelatedItems || (isActive && typingResponse));
  const className = cx(styles.AcInputContainer, showSuggestions && styles.opened);

  /**
   * Update the text input with the selected suggestion.
   */
  useEffect(() => {
    if (selected) {
      setValue(selected.value);
    }
  }, [name, selected]);

  /**
   * On input change update the state and request autocomplete suggestions.
   * @param e
   */
  function handleChange(e: FormEvent) {
    const value = ((e.target as HTMLInputElement).value || '').trim();
    setValue(value);
    onTyping(value);
  }

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
        onSelect={onSelect}
        style={{display: showSuggestions ? 'block' : 'none'}}
      />
    </div>
  );
}
