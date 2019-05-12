import { Suggestion, TypingResponse } from '../../types';
import styles from './Suggestions.module.scss';
import React from 'react';

interface SuggestionsProps {
  data: TypingResponse | null;
  selected: Suggestion | null;
  onSelect: { (suggestion: Suggestion): void };
  style: React.CSSProperties | undefined;
}

export default function Suggestions({ data, selected, onSelect, style }: SuggestionsProps) {
  if (!data) {
    return null;
  }
  const { hits, maxScore, total } = data;

  const items =
    hits.length > 0 ? (
      hits.map((item: Suggestion) => (
        <li key={item._id} className={selected && selected._id === item._id ? styles.active : ''}>
          <button className={styles.itemButton} onClick={() => onSelect(item)}>
            {item.value}
          </button>
        </li>
      ))
    ) : (
      <li>No results.</li>
    );

  const totalText = `${total.value}${total.relation === 'gte' ? '+' : ''}`;

  return (
    <div className={styles.Suggestions} style={style}>
      {hits.length > 0 && (
        <div>
          <b>Total:</b> {totalText} <br />
          <b>Max score:</b> {maxScore}
        </div>
      )}
      <ul className={styles.list}>{items}</ul>
    </div>
  );
}
