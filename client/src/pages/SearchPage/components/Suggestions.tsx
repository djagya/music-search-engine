import { SearchResponse, Suggestion } from '../../../types';
import styles from './Suggestions.module.scss';
import React from 'react';

interface SuggestionsProps {
  data: SearchResponse['suggestions'];
  selected: Suggestion | null;
  onSelect: { (suggestion: Suggestion): void };
  style: React.CSSProperties | undefined;
}

/**
 * A list of suggestions.
 */
export default function Suggestions({ data, selected, onSelect, style }: SuggestionsProps) {
  return (
    <div className={styles.Suggestions} style={style}>
      <ul className={styles.list}>
        {data.length === 0 && <li>No results.</li>}
        {data.length > 0 && data.map(_ => <ListItem key={_.id} item={_} active={selected} onSelect={onSelect} />)}
      </ul>
    </div>
  );
}

interface ListItemProps {
  active: Suggestion | null;
  item: Suggestion;
  onSelect: { (item: Suggestion): void };
}

function ListItem({ active, item, onSelect }: ListItemProps) {
  return (
    <li className={active && active.id === item.id ? styles.active : ''}>
      <button className={styles.itemButton} onClick={() => onSelect(item)}>
        {item.value}
        <small className={styles.itemMeta}>
          [{item.score !== 1 ? `score: ${item.score}; ` : ''}n: {item.count}; {item._index}]
        </small>
      </button>
    </li>
  );
}
