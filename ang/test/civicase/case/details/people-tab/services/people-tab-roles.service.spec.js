/* eslint-env jasmine */

((_) => {
  describe('PeopleTabRoles', () => {
    let caseItem, caseType, peopleTabRoles, relationships, relationshipTypes;

    beforeEach(module('civicase', 'civicase.data'));

    beforeEach(inject((_CasesData_, _CaseTypesMockData_,
      _civicasePeopleTabRoles_, _RelationshipTypeData_) => {
      peopleTabRoles = _civicasePeopleTabRoles_;
      caseItem = _.first(_CasesData_.get().values);
      caseType = _CaseTypesMockData_.get()['1'];
      relationshipTypes = _RelationshipTypeData_.values;
    }));

    beforeEach(() => {
      var relationshipType = _.find(relationshipTypes, {
        name_b_a: caseItem.contacts[1].role
      });
      relationships = [
        {
          'api.Contact.get': { count: 1, values: [caseItem.contacts[1]] },
          contact_id_a: caseItem.contacts[0].contact_id,
          contact_id_b: caseItem.contacts[1].contact_id,
          is_active: '1',
          relationship_type_id: relationshipType.id
        }
      ];

      peopleTabRoles.setCaseContacts(caseItem.contacts);
      peopleTabRoles.setCaseRelationships(relationships);
      peopleTabRoles.setCaseTypeRoles(caseType.definition.caseRoles);
    });

    describe('on init', () => {
      it('defines the list of roles as empty', () => {
        expect(peopleTabRoles.list).toEqual([]);
      });

      it('defines the records per page as 25', () => {
        expect(peopleTabRoles.ROLES_PER_PAGE).toBe(25);
      });

      it('defines the total amount of records as 0', () => {
        expect(peopleTabRoles.totalCount).toBe(0);
      });

      it('defines the loading state as true', () => {
        expect(peopleTabRoles.isLoading).toBe(true);
      });
    });

    describe('roles list', () => {
      describe('when the case client has been assigned', () => {
        let caseClient;

        beforeEach(() => {
          caseClient = _.find(caseItem.contacts, { role: 'Client' });

          peopleTabRoles.updateRolesList();
        });

        it('stores the case client in the list of roles', () => {
          expect(peopleTabRoles.list).toContain(jasmine.objectContaining({
            contact_id: caseClient.contact_id,
            description: null,
            desc: null,
            display_name: caseClient.display_name,
            email: caseClient.email,
            phone: caseClient.phone,
            role: 'Client',
            start_date: null
          }));
        });
      });

      describe('when the case manager has been assigned', () => {
        let caseManager, caseTypeRole, caseRelation;

        beforeEach(() => {
          caseManager = _.find(caseItem.contacts, { manager: '1' });
          caseTypeRole = _.find(caseType.definition.caseRoles, {
            name: caseManager.role
          });
          caseRelation = _.find(relationships, {
            relationship_type_id: _.find(relationshipTypes, {
              name_b_a: caseTypeRole.name
            }).id
          });

          peopleTabRoles.updateRolesList();
        });

        it('stores the case manager in the list of roles', () => {
          expect(peopleTabRoles.list).toContain(jasmine.objectContaining({
            contact_id: caseRelation.contact_id_b,
            description: `Case Manager. ${caseTypeRole.name}`,
            desc: caseRelation.description,
            display_name: caseManager.display_name,
            email: caseManager.email,
            isPastRole: caseRelation.is_active === '0',
            phone: caseManager.phone,
            relationship_type_id: caseRelation.relationship_type_id,
            role: caseTypeRole.name,
            start_date: caseRelation.start_date
          }));
        });
      });

      describe('when the role has not been assigned', () => {
        let caseTypeRole, relationshipType;

        beforeEach(() => {
          caseTypeRole = _.find(caseType.definition.caseRoles, {
            manager: '1'
          });
          relationshipType = _.find(relationshipTypes, {
            name_b_a: caseTypeRole.name
          });

          peopleTabRoles.setCaseRelationships([]);

          peopleTabRoles.updateRolesList();
        });

        it('includes the empty role', () => {
          expect(peopleTabRoles.list).toContain(jasmine.objectContaining({
            description: `Case Manager. ${caseTypeRole.name}`,
            relationship_type_id: relationshipType.id,
            role: caseTypeRole.name
          }));
        });
      });
    });

    describe('filtering', () => {
      describe('when filtering roles by type', () => {
        beforeEach(() => {
          peopleTabRoles.updateRolesList();
          peopleTabRoles.filterRoles('', 'Client');
        });

        it('only contains the roles for that type', () => {
          expect(peopleTabRoles.list).toEqual([jasmine.objectContaining({
            role: 'Client'
          })]);
        });
      });

      describe('when filtering by letter', () => {
        let caseClient;

        beforeEach(() => {
          caseClient = _.find(caseItem.contacts, { role: 'Client' });

          peopleTabRoles.updateRolesList();
          peopleTabRoles.filterRoles(caseClient.display_name[0], '');
        });

        it('only contains the roles that match the given letter', () => {
          expect(peopleTabRoles.list).toEqual([jasmine.objectContaining({
            display_name: caseClient.display_name
          })]);
        });
      });

      describe('when filtering by letter and role type', () => {
        let caseClient;

        beforeEach(() => {
          caseClient = _.find(caseItem.contacts, { role: 'Client' });

          peopleTabRoles.updateRolesList();
          peopleTabRoles.filterRoles(caseClient.display_name[0], 'Client');
        });

        it('only contains the roles that match the given letter', () => {
          expect(peopleTabRoles.list).toEqual([jasmine.objectContaining({
            display_name: caseClient.display_name,
            role: 'Client'
          })]);
        });
      });
    });

    describe('pagination', () => {
      let contacts, expectedRoles;

      beforeEach(() => {
        contacts = _.range(1, peopleTabRoles.ROLES_PER_PAGE * 2)
          .map((index) => ({
            contact_id: index,
            role: 'Client'
          }));
        relationships = contacts
          .map((contact) => (_.assign({}, relationships[0], {
            contact_id_b: contact.contact_id,
            'api.Contact.get': {
              values: [contact]
            }
          })));

        peopleTabRoles.setCaseContacts(contacts);
        peopleTabRoles.setCaseRelationships(relationships);
        peopleTabRoles.updateRolesList();
      });

      describe('when requesting the first page', () => {
        beforeEach(() => {
          expectedRoles = _.range(1, peopleTabRoles.ROLES_PER_PAGE)
            .map((index) => jasmine.objectContaining({
              contact_id: index
            }));

          peopleTabRoles.updateRolesList();
          peopleTabRoles.goToPage(1);
        });

        it('only displays the roles belonging to the first page', () => {
          expect(peopleTabRoles.list)
            .toEqual(jasmine.arrayContaining(expectedRoles));
        });
      });

      describe('when requesting the second page', () => {
        beforeEach(() => {
          expectedRoles = _.range(
            peopleTabRoles.ROLES_PER_PAGE + 1,
            peopleTabRoles.ROLES_PER_PAGE * 2
          )
            .map((index) => jasmine.objectContaining({
              contact_id: index
            }));

          peopleTabRoles.updateRolesList();
          peopleTabRoles.goToPage(2);
        });

        it('only displays the roles belonging to the second page', () => {
          expect(peopleTabRoles.list)
            .toEqual(jasmine.arrayContaining(expectedRoles));
        });
      });
    });
  });
})(CRM._);
