import React, { useState, useEffect } from 'react';

interface CatalogItem {
  id: number;
  name: string;
  unit_price: number;
  unit_cost: number;
  type: string;
}

interface AddComponentFormProps {
  type: 'product' | 'service' | 'recurring-service' | 'repeat-product';
  catalogItems: CatalogItem[];
  onSuccess: () => void;
  onCancel: () => void;
  quoteId: number;
  initialTopology?: 'SIMPLE' | 'COMPLEX';
}

interface PhaseUnitFormState {
  title: string;
  description: string;
  quantity: number;
  unitPrice: number;
  unitCost: number;
}

interface PhaseFormState {
  name: string;
  description: string;
  units: PhaseUnitFormState[];
}

const AddComponentForm: React.FC<AddComponentFormProps> = ({ type, catalogItems, onSuccess, onCancel, quoteId, initialTopology }) => {
  const [selectedItemId, setSelectedItemId] = useState<string>('');
  const [description, setDescription] = useState('');
  const [section, setSection] = useState('General');
  const [quantity, setQuantity] = useState(1);
  const [unitPrice, setUnitPrice] = useState(0);
  const [unitCost, setUnitCost] = useState(0);
  
  // Recurring specific fields
  const [serviceName, setServiceName] = useState('');
  const [cadence, setCadence] = useState('monthly');
  const [termMonths, setTermMonths] = useState(12);
  const [renewalModel, setRenewalModel] = useState('auto');
  
  const [topology, setTopology] = useState<'SIMPLE' | 'COMPLEX'>(initialTopology ?? 'SIMPLE');
  const [phases, setPhases] = useState<PhaseFormState[]>([
    {
      name: 'Phase 1',
      description: '',
      units: [
        {
          title: '',
          description: '',
          quantity: 1,
          unitPrice: 0,
          unitCost: 0,
        },
      ],
    },
  ]);
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (selectedItemId) {
      const item = catalogItems.find(i => i.id.toString() === selectedItemId);
      if (item) {
        setDescription(item.name);
        setUnitPrice(item.unit_price);
        setUnitCost(item.unit_cost);
        setServiceName(item.name);
      }
    }
  }, [selectedItemId, catalogItems]);

  const addPhase = () => {
    setPhases(prev => [
      ...prev,
      {
        name: `Phase ${prev.length + 1}`,
        description: '',
        units: [
          {
            title: '',
            description: '',
            quantity: 1,
            unitPrice: 0,
            unitCost: 0,
          },
        ],
      },
    ]);
  };

  const removePhase = (index: number) => {
    setPhases(prev => prev.filter((_, i) => i !== index));
  };

  const updatePhaseField = (index: number, field: keyof PhaseFormState, value: string) => {
    setPhases(prev =>
      prev.map((phase, i) =>
        i === index
          ? {
              ...phase,
              [field]: value,
            }
          : phase
      )
    );
  };

  const addUnitToPhase = (phaseIndex: number) => {
    setPhases(prev =>
      prev.map((phase, i) =>
        i === phaseIndex
          ? {
              ...phase,
              units: [
                ...phase.units,
                {
                  title: '',
                  description: '',
                  quantity: 1,
                  unitPrice: 0,
                  unitCost: 0,
                },
              ],
            }
          : phase
      )
    );
  };

  const removeUnitFromPhase = (phaseIndex: number, unitIndex: number) => {
    setPhases(prev =>
      prev.map((phase, i) =>
        i === phaseIndex
          ? {
              ...phase,
              units: phase.units.filter((_, u) => u !== unitIndex),
            }
          : phase
      )
    );
  };

  const updateUnitField = (
    phaseIndex: number,
    unitIndex: number,
    field: keyof PhaseUnitFormState,
    value: string
  ) => {
    setPhases(prev =>
      prev.map((phase, i) =>
        i === phaseIndex
          ? {
              ...phase,
              units: phase.units.map((unit, u) =>
                u === unitIndex
                  ? {
                      ...unit,
                      [field]:
                        field === 'quantity' || field === 'unitPrice' || field === 'unitCost'
                          ? Number(value) || 0
                          : value,
                    }
                  : unit
              ),
            }
          : phase
      )
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // @ts-ignore
      const apiUrl = window.petSettings?.apiUrl;
      // @ts-ignore
      const nonce = window.petSettings?.nonce;

      let payload: any = {};

      if (type === 'product') {
        payload = {
          type: 'catalog',
          data: {
            section,
            description: 'Product Line Item',
            items: [
              {
                description,
                quantity,
                unit_sell_price: unitPrice,
                unit_internal_cost: unitCost,
                catalog_item_id: selectedItemId ? parseInt(selectedItemId) : null
              }
            ]
          }
        };
      } else if (type === 'service') {
        if (topology === 'SIMPLE') {
          payload = {
            type: 'once_off_service',
            data: {
              section,
              description,
              topology: 'SIMPLE',
              units: [
                {
                  title: description,
                  description,
                  quantity,
                  unit_sell_price: unitPrice,
                  unit_internal_cost: unitCost,
                },
              ],
            },
          };
        } else {
          payload = {
            type: 'once_off_service',
            data: {
              section,
              description,
              topology: 'COMPLEX',
              phases: phases.map(phase => ({
                name: phase.name || 'Phase',
                description: phase.description || undefined,
                units: phase.units.map(unit => ({
                  title: unit.title,
                  description: unit.description || undefined,
                  quantity: unit.quantity,
                  unit_sell_price: unit.unitPrice,
                  unit_internal_cost: unit.unitCost,
                })),
              })),
            },
          };
        }
      } else if (type === 'recurring-service' || type === 'repeat-product') {
        // Map to RecurringServiceComponent
        payload = {
          type: 'recurring',
          data: {
            service_name: serviceName || description,
            cadence,
            term_months: termMonths,
            renewal_model: renewalModel,
            sell_price_per_period: unitPrice * quantity, // Assuming quantity applies to price per period
            internal_cost_per_period: unitCost * quantity,
            description: type === 'repeat-product' ? 'Repeat Product Subscription' : 'Recurring Service',
            section
          }
        };
      }

      const response = await fetch(`${apiUrl}/quotes/${quoteId}/components`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.error || 'Failed to add component');
      }

      onSuccess();
    } catch (err) {
      console.error('AddComponentForm: Error', err);
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setLoading(false);
    }
  };

  const filteredItems = catalogItems.filter(item => {
    if (type === 'product' || type === 'repeat-product') return item.type === 'product';
    if (type === 'service' || type === 'recurring-service') return item.type === 'service';
    return true;
  });

  return (
    <form onSubmit={handleSubmit}>
      {error && <div className="notice notice-error inline"><p>{error}</p></div>}
      
      <div style={{ marginBottom: '15px' }}>
        <label style={{ display: 'block', marginBottom: '5px' }}>Section</label>
        <input 
          type="text" 
          value={section} 
          onChange={(e) => setSection(e.target.value)}
          style={{ width: '100%' }}
          list="section-suggestions"
        />
        <datalist id="section-suggestions">
            <option value="General" />
            <option value="Hardware" />
            <option value="Software" />
            <option value="Labor" />
            <option value="Managed Services" />
        </datalist>
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="component-select" style={{ display: 'block', marginBottom: '5px' }}>
          Select {type.includes('product') ? 'Product' : 'Service'}
        </label>
        <select 
          id="component-select"
          value={selectedItemId} 
          onChange={(e) => setSelectedItemId(e.target.value)}
          style={{ width: '100%' }}
          required={!serviceName && (type === 'recurring-service' ? false : true)} 
        >
          <option value="">-- Select --</option>
          {filteredItems.map(item => (
            <option key={item.id} value={item.id}>{item.name} (${item.unit_price})</option>
          ))}
        </select>
      </div>

      {type === 'service' && topology === 'COMPLEX' && (
        <div style={{ marginBottom: '15px' }}>
          {phases.map((phase, phaseIndex) => (
            <div
              key={phaseIndex}
              className="card"
              style={{ padding: '15px', marginBottom: '10px', background: '#fff', border: '1px solid #ccd0d4' }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ flex: 1, marginRight: '10px' }}>
                  <label style={{ display: 'block', marginBottom: '5px' }}>Phase Name</label>
                  <input
                    type="text"
                    value={phase.name}
                    onChange={e => updatePhaseField(phaseIndex, 'name', e.target.value)}
                    style={{ width: '100%' }}
                  />
                </div>
                {phases.length > 1 && (
                  <button
                    type="button"
                    className="button button-link-delete"
                    onClick={() => removePhase(phaseIndex)}
                  >
                    Remove Phase
                  </button>
                )}
              </div>
              <div style={{ marginTop: '10px' }}>
                <label style={{ display: 'block', marginBottom: '5px' }}>Phase Description</label>
                <input
                  type="text"
                  value={phase.description}
                  onChange={e => updatePhaseField(phaseIndex, 'description', e.target.value)}
                  style={{ width: '100%' }}
                />
              </div>
              <div style={{ marginTop: '10px' }}>
                <table className="widefat fixed striped">
                  <thead>
                    <tr>
                      <th>Unit Title</th>
                      <th>Quantity</th>
                      <th>Unit Price</th>
                      <th>Unit Cost</th>
                      <th>Description</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {phase.units.map((unit, unitIndex) => (
                      <tr key={unitIndex}>
                        <td>
                          <input
                            type="text"
                            value={unit.title}
                            onChange={e =>
                              updateUnitField(phaseIndex, unitIndex, 'title', e.target.value)
                            }
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            min={1}
                            value={unit.quantity}
                            onChange={e =>
                              updateUnitField(phaseIndex, unitIndex, 'quantity', e.target.value)
                            }
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            step="0.01"
                            value={unit.unitPrice}
                            onChange={e =>
                              updateUnitField(phaseIndex, unitIndex, 'unitPrice', e.target.value)
                            }
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td>
                          <input
                            type="number"
                            step="0.01"
                            value={unit.unitCost}
                            onChange={e =>
                              updateUnitField(phaseIndex, unitIndex, 'unitCost', e.target.value)
                            }
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            value={unit.description}
                            onChange={e =>
                              updateUnitField(phaseIndex, unitIndex, 'description', e.target.value)
                            }
                            style={{ width: '100%' }}
                          />
                        </td>
                        <td>
                          {phase.units.length > 1 && (
                            <button
                              type="button"
                              className="button button-link-delete"
                              onClick={() => removeUnitFromPhase(phaseIndex, unitIndex)}
                            >
                              Remove
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <div style={{ marginTop: '10px' }}>
                  <button
                    type="button"
                    className="button"
                    onClick={() => addUnitToPhase(phaseIndex)}
                  >
                    Add Unit
                  </button>
                </div>
              </div>
            </div>
          ))}
          <button type="button" className="button" onClick={addPhase}>
            Add Phase
          </button>
        </div>
      )}

      {(type === 'recurring-service' || type === 'repeat-product') && (
        <div style={{ marginBottom: '15px' }}>
            <label htmlFor="service-name" style={{ display: 'block', marginBottom: '5px' }}>Service/Product Name</label>
            <input 
                id="service-name"
                type="text" 
                value={serviceName} 
                onChange={(e) => setServiceName(e.target.value)}
                style={{ width: '100%' }}
                required
            />
        </div>
      )}

      <div style={{ marginBottom: '15px' }}>
        <label htmlFor="component-description" style={{ display: 'block', marginBottom: '5px' }}>Description</label>
        <input 
          id="component-description"
          type="text" 
          value={description} 
          onChange={(e) => setDescription(e.target.value)}
          style={{ width: '100%' }}
          required
        />
      </div>

      <div style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
        <div style={{ flex: 1 }}>
          <label htmlFor="component-quantity" style={{ display: 'block', marginBottom: '5px' }}>Quantity</label>
          <input 
            id="component-quantity"
            type="number" 
            value={quantity} 
            onChange={(e) => setQuantity(parseInt(e.target.value) || 1)}
            style={{ width: '100%' }}
            min="1"
            required
          />
        </div>
        <div style={{ flex: 1 }}>
          <label htmlFor="component-price" style={{ display: 'block', marginBottom: '5px' }}>Unit Price</label>
          <input 
            id="component-price"
            type="number" 
            value={unitPrice} 
            onChange={(e) => setUnitPrice(parseFloat(e.target.value) || 0)}
            style={{ width: '100%' }}
            step="0.01"
            required
          />
        </div>
      </div>

      {(type === 'recurring-service' || type === 'repeat-product') && (
        <>
            <div style={{ display: 'flex', gap: '15px', marginBottom: '15px' }}>
                <div style={{ flex: 1 }}>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Cadence</label>
                    <select 
                        value={cadence} 
                        onChange={(e) => setCadence(e.target.value)}
                        style={{ width: '100%' }}
                    >
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annually">Annually</option>
                    </select>
                </div>
                <div style={{ flex: 1 }}>
                    <label style={{ display: 'block', marginBottom: '5px' }}>Term (Months)</label>
                    <input 
                        type="number" 
                        value={termMonths} 
                        onChange={(e) => setTermMonths(parseInt(e.target.value) || 12)}
                        style={{ width: '100%' }}
                        min="1"
                    />
                </div>
            </div>
             <div style={{ marginBottom: '15px' }}>
                <label style={{ display: 'block', marginBottom: '5px' }}>Renewal Model</label>
                <select 
                    value={renewalModel} 
                    onChange={(e) => setRenewalModel(e.target.value)}
                    style={{ width: '100%' }}
                >
                    <option value="auto">Auto-Renew</option>
                    <option value="manual">Manual</option>
                    <option value="expire">Expire</option>
                </select>
            </div>
        </>
      )}

      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
        <button type="button" className="button" onClick={onCancel} disabled={loading}>Cancel</button>
        <button type="submit" className="button button-primary" disabled={loading}>
          {loading ? 'Adding...' : 'Add Component'}
        </button>
      </div>
    </form>
  );
};

export default AddComponentForm;
