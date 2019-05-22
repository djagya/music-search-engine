import { SearchResponse, Suggestion } from '../../types';
import styles from './Suggestions.module.scss';
import React from 'react';

interface SuggestionsProps {
  data: SearchResponse | null;
  selected: Suggestion | null;
  showAggregations: boolean;
  onSelect: { (suggestion: Suggestion): void };
  style: React.CSSProperties | undefined;
}

export default function Suggestions({ data, selected, showAggregations, onSelect, style }: SuggestionsProps) {
  if (!data) {
    return null;
  }
  const { aggregations, hits, maxScore, total } = data;

  const listItems = (items: Suggestion[]) =>
    items.length ? (
      items.map(_ => <ListItem key={_.id} item={_} active={selected} onSelect={onSelect} />)
    ) : (
      <li>No results.</li>
    );

  const totalText = `${total.value}${total.relation === 'gte' ? '+' : ''}`;

  return (
    <div className={styles.Suggestions} style={style}>
      <small>{showAggregations ? 'Showing unique aggregation' : 'Showing hits'}</small>

      {hits.length > 0 && (
        <div>
          <b>Total:</b> {totalText} <br />
          <b>Max score:</b> {maxScore}
        </div>
      )}
      <ul className={styles.list}>{listItems(showAggregations ? aggregations : hits)}</ul>
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
          [score: {item.score}; count: {item.count}]
        </small>
      </button>
    </li>
  );
}
