import { SearchResponse, Suggestion } from '../../types';
import styles from './Suggestions.module.scss';
import React from 'react';
import { formatTotal } from '../../utils';

interface SuggestionsProps {
  data: SearchResponse | null;
  selected: Suggestion | null;
  onSelect: { (suggestion: Suggestion): void };
  style: React.CSSProperties | undefined;
}

export default function Suggestions({ data, selected, onSelect, style }: SuggestionsProps) {
  if (!data) {
    return null;
  }
  const { total, took, suggestions } = data;

  const listItems = (items: Suggestion[]) =>
    items.length ? (
      items.map(_ => <ListItem key={_.id} item={_} active={selected} onSelect={onSelect} />)
    ) : (
      <li>No results.</li>
    );

  return (
    <div className={styles.Suggestions} style={style}>
      {suggestions.length > 0 && (
        <div>
          <small>
            <b>total</b> {formatTotal(total)}&nbsp;&nbsp;<b>took</b> {took}ms
          </small>
        </div>
      )}
      <ul className={styles.list}>{listItems(suggestions)}</ul>
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
          [score: {item.score}; count: {item.count}; {item._index}]
        </small>
      </button>
    </li>
  );
}
